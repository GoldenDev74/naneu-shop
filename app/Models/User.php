<?php

namespace App\Models;

use App\Enums\AccessLevel;
use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class User extends Authenticatable implements HasLocalePreference
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, SoftDeletes;

    /** @var list<string> */
    protected $fillable = [
        'name',
        'username',
        'email',
        'password',
        'role',
        'phone',
        'locale',
        'is_active',
        'must_change_password',
    ];

    /**
     * Ni le mot de passe haché ni le jeton « se souvenir de moi » ne doivent
     * sortir du modèle, y compris dans les charges utiles diffusées par Reverb.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'email_verified_at'    => 'datetime',
            'password'             => 'hashed',
            'role'                 => UserRole::class,
            'permissions'          => 'array',
            'locked_until'         => 'datetime',
            'is_active'            => 'boolean',
            'must_change_password' => 'boolean',
            'last_login_at'        => 'datetime',
        ];
    }

    /** Un compte créé sans nom d'utilisateur (console, import) en reçoit un. */
    protected static function booted(): void
    {
        static::creating(function (User $user) {
            $user->username = filled($user->username)
                ? Str::lower(trim($user->username))
                : static::suggestUsername($user->email, $user->name);
        });
    }

    /** Identifiant libre tiré de l'e-mail (partie locale) ou, à défaut, du nom. */
    public static function suggestUsername(?string $email, ?string $name): string
    {
        $base = Str::of($email ? Str::before($email, '@') : (string) $name)
            ->ascii()->lower()->replaceMatches('/[^a-z0-9._-]+/', '.')->trim('.')->limit(24, '')->value();

        $base = strlen($base) >= 3 ? $base : 'utilisateur';
        $candidat = $base;
        $suffixe = 2;

        while (static::withTrashed()->where('username', $candidat)->exists()) {
            $candidat = $base.$suffixe++;
        }

        return $candidat;
    }

    // -----------------------------------------------------------------
    // Relations
    // -----------------------------------------------------------------

    /** @return HasMany<StockEntry, $this> */
    public function stockEntries(): HasMany
    {
        return $this->hasMany(StockEntry::class);
    }

    /** @return HasMany<StockExit, $this> */
    public function stockExits(): HasMany
    {
        return $this->hasMany(StockExit::class);
    }

    /** @return HasMany<StockMovement, $this> */
    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    /** @return HasMany<AuditLog, $this> */
    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    // -----------------------------------------------------------------
    // Rôles
    // -----------------------------------------------------------------

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    // -----------------------------------------------------------------
    // Droits par menu
    // -----------------------------------------------------------------

    /**
     * Droit sur un menu. La gérance a tout ; un employé a ce que la gérance
     * lui a attribué (permissions), à défaut les droits de son profil.
     * Seule source de vérité : menu latéral, routes, composants et boutons.
     */
    public function accessLevel(string $section): ?AccessLevel
    {
        if (! $this->is_active) {
            return null;
        }

        if ($this->role->isManagement()) {
            return AccessLevel::Complet;
        }

        if (! array_key_exists($section, UserRole::SECTIONS_ATTRIBUABLES)) {
            return null;
        }

        $permissions = $this->customPermissions();

        $niveau = $permissions !== null
            ? AccessLevel::tryFrom((string) ($permissions[$section] ?? ''))
            : ($this->role->defaultPermissions()[$section] ?? null);

        // Lots et mouvements se consultent seulement.
        if ($niveau && in_array($section, UserRole::SECTIONS_CONSULTATION, true)) {
            return AccessLevel::Lecture;
        }

        return $niveau;
    }

    /**
     * Grille attribuée par la gérance, null si le compte suit son profil.
     * (Un modèle tout juste créé n'a pas encore la colonne chargée.)
     *
     * @return array<string, string>|null
     */
    public function customPermissions(): ?array
    {
        return array_key_exists('permissions', $this->attributes) ? $this->permissions : null;
    }

    /** Voit le menu (lecture ou complet). */
    public function canAccess(string $section): bool
    {
        return $this->accessLevel($section) !== null;
    }

    /** Peut créer, modifier, supprimer dans ce menu. */
    public function canWrite(string $section): bool
    {
        return $this->accessLevel($section) === AccessLevel::Complet;
    }

    /**
     * Droits effectifs de chaque menu attribuable, pour le formulaire.
     *
     * @return array<string, string> section => 'lecture'|'complet'|''
     */
    public function permissionMatrix(): array
    {
        return collect(UserRole::SECTIONS_ATTRIBUABLES)
            ->map(fn ($libelle, $section) => $this->accessLevel($section)?->value ?? '')
            ->all();
    }

    /** Page d'arrivée : le tableau de bord, sinon la caisse, sinon le premier menu ouvert. */
    public function homeRoute(): string
    {
        foreach (['tableau-de-bord' => 'tableau-de-bord', 'caisse' => 'caisse'] as $section => $route) {
            if ($this->canAccess($section)) {
                return $route;
            }
        }

        foreach (array_keys(UserRole::SECTIONS_ATTRIBUABLES) as $section) {
            if ($this->canAccess($section)) {
                return "{$section}.index";
            }
        }

        return 'profile';
    }

    // -----------------------------------------------------------------
    // Verrouillage après échecs de connexion
    // -----------------------------------------------------------------

    public function isLocked(): bool
    {
        return array_key_exists('locked_until', $this->attributes)
            && $this->locked_until !== null
            && $this->locked_until->isFuture();
    }

    /**
     * Ferme les sessions ouvertes de ce compte (autres appareils compris),
     * sauf éventuellement la session en cours : après un changement de mot
     * de passe, l'ancien ne doit plus donner accès nulle part.
     */
    public function closeSessions(?string $saufSession = null): void
    {
        if (config('session.driver') !== 'database') {
            return;
        }

        DB::table(config('session.table', 'sessions'))
            ->where('user_id', $this->id)
            ->when($saufSession, fn ($q) => $q->where('id', '!=', $saufSession))
            ->delete();
    }

    /**
     * Gère le compte d'un autre : l'administrateur gère tout le monde, la
     * gérance uniquement les profils qu'elle peut attribuer (employés).
     */
    public function manages(User $target): bool
    {
        return $this->is_active && in_array($target->role, $this->role->assignableRoles(), true);
    }

    /**
     * Vrai si l'utilisateur possède au moins le niveau du rôle demandé.
     * La hiérarchie vit dans l'enum, pas ici.
     */
    public function hasAtLeast(UserRole $role): bool
    {
        return $this->role->level() >= $role->level();
    }

    /** @param  array<int, UserRole>  $roles */
    public function hasAnyRole(array $roles): bool
    {
        return in_array($this->role, $roles, true);
    }

    // -----------------------------------------------------------------
    // Scopes
    // -----------------------------------------------------------------

    /** @param  Builder<User>  $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /** @param  Builder<User>  $query */
    public function scopeRole(Builder $query, UserRole $role): void
    {
        $query->where('role', $role->value);
    }

    /**
     * Destinataires des alertes de stock : admins et gérants actifs.
     *
     * @param  Builder<User>  $query
     */
    public function scopeAlertRecipients(Builder $query): void
    {
        $query->where('is_active', true)
            ->whereIn('role', [UserRole::Admin->value, UserRole::Gerant->value]);
    }

    // -----------------------------------------------------------------
    // Divers
    // -----------------------------------------------------------------

    public function initials(): string
    {
        return collect(explode(' ', trim($this->name)))
            ->filter()
            ->take(2)
            ->map(fn (string $part) => mb_strtoupper(mb_substr($part, 0, 1)))
            ->implode('');
    }

    /** Langue des e-mails et notifications envoyés à cet utilisateur. */
    public function preferredLocale(): ?string
    {
        return $this->getAttributes()['locale'] ?? null;
    }
}
