<?php

namespace App\Livewire\Users;

use App\Enums\AccessLevel;
use App\Enums\UserRole;
use App\Livewire\Concerns\InteractsWithToasts;
use App\Livewire\Concerns\WithColumnFilters;
use App\Models\AuditLog;
use App\Models\User;
use App\Notifications\AccountCreatedNotification;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Utilisateurs et accès')]
class UserManager extends Component
{
    use InteractsWithToasts, WithPagination, WithColumnFilters;

    #[Url(as: 'q', except: '')]
    public string $recherche = '';

    #[Url(except: '')]
    public string $filtreRole = '';

    public bool $panneau = false;
    public ?int $enEdition = null;

    public string $name = '';
    public string $username = '';
    public string $email = '';
    public string $phone = '';
    public string $role = 'caissier';
    public bool $is_active = true;

    /**
     * Droits par menu attribuables : section => '' (aucun) | 'lecture' | 'complet'.
     * Préremplis avec ceux du profil, ajustables un par un.
     *
     * @var array<string, string>
     */
    public array $droits = [];

    /** Mot de passe saisi manuellement (vide si génération automatique). */
    public string $password = '';
    public string $password_confirmation = '';
    public bool $genererMotDePasse = true;
    public bool $must_change_password = true;

    /** Fenêtre « Mot de passe » : compte visé et méthode choisie. */
    public ?int $mdpPour = null;
    public string $mdpMode = 'generer';
    public string $nouveauMdp = '';
    public string $nouveauMdp_confirmation = '';
    public bool $mdpExigerChangement = true;

    /** Mot de passe généré, affiché une seule fois après création. */
    public ?string $motDePasseAffiche = null;

    /** Le bandeau dit vrai : courriel parti ou non, changement exigé ou non. */
    public bool $mdpEnvoye = false;
    public bool $mdpAChanger = true;
    public ?string $compteAffiche = null;

    public function mount(): void
    {
        $this->authorize('viewAny', User::class);

        $this->droitsDuProfil();
    }

    public function updated(string $propriete): void
    {
        if (in_array($propriete, ['recherche', 'filtreRole'], true)) {
            $this->resetPage();
        }
    }

    /** @return array<string, mixed> */
    protected function rules(): array
    {
        return [
            'name'     => ['required', 'string', 'max:120'],
            'username' => [
                'required', 'string', 'min:3', 'max:30', 'regex:/^[a-z0-9._-]+$/',
                Rule::unique('users', 'username')->ignore($this->enEdition),
            ],
            // Facultatif : un employé sans adresse se connecte avec son nom d'utilisateur.
            'email' => [
                'nullable', 'email', 'max:150',
                Rule::unique('users', 'email')->ignore($this->enEdition)->withoutTrashed(),
            ],
            'phone'     => ['nullable', 'string', 'regex:/^[0-9+\s().-]{6,25}$/'],
            'role'      => ['required', Rule::enum(UserRole::class)],
            'droits'    => ['array'],
            'droits.*'  => ['nullable', Rule::in(['', ...array_column(AccessLevel::cases(), 'value')])],
            'is_active' => ['boolean'],

            'password' => [
                Rule::when(
                    ! $this->genererMotDePasse && ($this->enEdition === null || filled($this->password)),
                    ['required', 'confirmed', PasswordRule::defaults()],
                    ['nullable'],
                ),
            ],
        ];
    }

    /**
     * Droits à stocker : null quand ils sont ceux du profil (le compte suit
     * alors le profil), sinon la grille saisie. La gérance n'a pas de grille.
     *
     * @return array<string, string>|null
     */
    private function permissionsAEnregistrer(UserRole $profil): ?array
    {
        if ($profil->isManagement()) {
            return null;
        }

        $grille = collect(UserRole::SECTIONS_ATTRIBUABLES)
            ->mapWithKeys(function ($libelle, $section) {
                $niveau = AccessLevel::tryFrom((string) ($this->droits[$section] ?? ''));

                if ($niveau && in_array($section, UserRole::SECTIONS_CONSULTATION, true)) {
                    $niveau = AccessLevel::Lecture;
                }

                return [$section => $niveau?->value ?? ''];
            })
            ->all();

        return $grille === self::matrice($profil->defaultPermissions())
            ? null
            : array_filter($grille);
    }

    /** @return array<string, string> */
    protected function messages(): array
    {
        return [
            'name.required'      => 'Le nom est obligatoire.',
            'username.required'  => 'Le nom d\'utilisateur est obligatoire.',
            'username.min'       => 'Le nom d\'utilisateur doit compter au moins 3 caractères.',
            'username.regex'     => 'Nom d\'utilisateur : lettres minuscules sans accents, chiffres, point, tiret ou soulignement.',
            'username.unique'    => 'Ce nom d\'utilisateur est déjà pris (y compris par un compte supprimé).',
            'email.unique'       => 'Un compte utilise déjà cette adresse.',
            'password.confirmed' => 'Les deux mots de passe ne correspondent pas.',
            'phone.regex'        => 'Numéro de téléphone invalide.',
        ];
    }

    public function nouveau(): void
    {
        $this->authorize('create', User::class);

        $this->reinitialiser();
        $this->panneau = true;
    }

    public function editer(int $id): void
    {
        $utilisateur = User::findOrFail($id);

        $this->authorize('update', $utilisateur);

        $this->enEdition = $utilisateur->id;

        $this->fill([
            'name'      => $utilisateur->name,
            'username'  => $utilisateur->username,
            'email'     => (string) $utilisateur->email,
            'phone'     => (string) $utilisateur->phone,
            'role'      => $utilisateur->role->value,
            'is_active' => $utilisateur->is_active,
        ]);

        $this->droits = $utilisateur->permissionMatrix();

        $this->genererMotDePasse = false;
        $this->password = '';
        $this->password_confirmation = '';
        $this->panneau = true;
    }

    /** Changer de profil repart des droits de ce profil. */
    public function updatedRole(): void
    {
        $this->droitsDuProfil();
    }

    public function droitsDuProfil(): void
    {
        $this->droits = self::matrice(UserRole::tryFrom($this->role)?->defaultPermissions() ?? []);
    }

    /**
     * @param  array<string, \App\Enums\AccessLevel>  $niveaux
     * @return array<string, string>
     */
    private static function matrice(array $niveaux): array
    {
        return collect(UserRole::SECTIONS_ATTRIBUABLES)
            ->map(fn ($libelle, $section) => in_array($section, UserRole::SECTIONS_CONSULTATION, true) && isset($niveaux[$section])
                ? AccessLevel::Lecture->value
                : ($niveaux[$section] ?? null)?->value ?? '')
            ->all();
    }

    /** Saisie normalisée avant validation : minuscules, sans espaces. */
    public function updatedUsername(string $valeur): void
    {
        $this->username = Str::lower(trim($valeur));
    }

    public function enregistrer(): void
    {
        $this->username = Str::lower(trim($this->username));
        $this->email = trim($this->email);

        // Champ laissé vide à la création : identifiant proposé d'après
        // l'e-mail ou le nom, affiché ensuite avec le mot de passe.
        if ($this->enEdition === null && $this->username === '' && filled($this->email.$this->name)) {
            $this->username = User::suggestUsername($this->email ?: null, $this->name);
        }

        $donnees = $this->validate();

        $nouveau = $this->enEdition === null;

        $utilisateur = $nouveau ? new User : User::findOrFail($this->enEdition);

        $this->authorize($nouveau ? 'create' : 'update', $nouveau ? User::class : $utilisateur);

        // La gérance ne crée que des profils d'employés (Vente, Gestion de stock).
        if (($nouveau || $utilisateur->role->value !== $donnees['role'])
            && ! auth()->user()->can('assignRole', [User::class, UserRole::from($donnees['role'])])) {
            $this->addError('role', __('Vous ne pouvez pas attribuer ce profil.'));

            return;
        }

        // Changer le rôle ou l'état d'activation d'un compte passe par des
        // autorisations distinctes : c'est ce qui empêche un administrateur
        // de se rétrograder lui-même ou de supprimer le dernier admin actif.
        if (! $nouveau) {
            $roleChange = $utilisateur->role->value !== $donnees['role'];
            $actifChange = $utilisateur->is_active !== $donnees['is_active'];

            if ($roleChange && ! auth()->user()->can('changeRole', $utilisateur)) {
                $this->addError('role', 'Vous ne pouvez pas modifier ce rôle.');

                return;
            }

            if ($actifChange && ! auth()->user()->can('toggleActive', $utilisateur)) {
                $this->addError('is_active', 'Vous ne pouvez pas modifier l\'état de ce compte.');

                return;
            }
        }

        $motDePasseClair = null;

        if ($nouveau || $this->genererMotDePasse || filled($this->password)) {
            $motDePasseClair = $this->genererMotDePasse
                ? Str::password(14, symbols: false)
                : $this->password;
        }

        $utilisateur->fill([
            'name'      => $donnees['name'],
            'username'  => $donnees['username'],
            'email'     => $donnees['email'] ?: null,
            'phone'     => $donnees['phone'] ?: null,
            'role'      => $donnees['role'],
            'is_active' => $donnees['is_active'],
        ]);

        $droitsAvant = $nouveau ? null : $utilisateur->permissionMatrix();
        $utilisateur->permissions = $this->permissionsAEnregistrer(UserRole::from($donnees['role']));

        if ($motDePasseClair !== null) {
            $utilisateur->password = Hash::make($motDePasseClair);
            $utilisateur->must_change_password = $this->must_change_password;
        }

        if ($nouveau) {
            $utilisateur->email_verified_at = now();
        }

        $utilisateur->save();

        if (! $nouveau && $motDePasseClair !== null) {
            $utilisateur->closeSessions();
        }

        AuditLog::record(
            $nouveau ? 'utilisateur.cree' : 'utilisateur.modifie',
            auth()->user(),
            $utilisateur,
            ['identifiant' => $utilisateur->username, 'email' => $utilisateur->email, 'role' => $utilisateur->role->value],
        );

        if (! $nouveau && $droitsAvant !== $utilisateur->permissionMatrix()) {
            AuditLog::record('utilisateur.droits_modifies', auth()->user(), $utilisateur, [
                'identifiant' => $utilisateur->username,
                'avant'       => array_filter($droitsAvant),
                'apres'       => array_filter($utilisateur->permissionMatrix()),
            ]);
        }

        // Le mot de passe en clair ne quitte jamais cet écran : il est
        // affiché une fois à l'administrateur, et envoyé par courriel au
        // titulaire. Il n'est écrit nulle part ailleurs.
        if ($nouveau && $motDePasseClair !== null) {
            $this->motDePasseAffiche = $motDePasseClair;
            $this->mdpAChanger = $utilisateur->must_change_password;
            $this->mdpEnvoye = false;
            $this->compteAffiche = $utilisateur->username;

            if (filled($utilisateur->email)) {
                $this->envoyerIdentifiants($utilisateur, $motDePasseClair);
            }
        }

        $this->toastSucces($nouveau ? 'Compte créé.' : 'Compte mis à jour.');

        $this->reinitialiser();
        $this->panneau = false;
    }

    /**
     * Envoie un lien de réinitialisation. Préféré à la génération d'un
     * nouveau mot de passe : personne, pas même l'administrateur, n'a
     * alors connaissance du secret choisi par l'utilisateur.
     */
    public function envoyerLienReinitialisation(int $id): void
    {
        $utilisateur = User::findOrFail($id);

        $this->authorize('resetPassword', $utilisateur);

        if (blank($utilisateur->email)) {
            $this->toastErreur('Ce compte n\'a pas d\'adresse e-mail : générez ou saisissez un mot de passe provisoire.');

            return;
        }

        $statut = Password::sendResetLink(['email' => $utilisateur->email]);

        AuditLog::record('mot_de_passe.lien_envoye', auth()->user(), $utilisateur, [
            'email' => $utilisateur->email,
        ]);

        if ($statut === Password::RESET_LINK_SENT) {
            $this->toastSucces(__('Lien de réinitialisation envoyé à :email.', ['email' => $utilisateur->email]));

            return;
        }

        $this->toastErreur('Le lien n\'a pas pu être envoyé. Vérifiez la configuration du courriel.');
    }

    /**
     * Repli lorsque le courriel n'est pas configuré : génère un mot de
     * passe provisoire, affiché une fois, à remettre de la main à la main.
     */
    public function genererMotDePasseProvisoire(int $id): void
    {
        $utilisateur = User::findOrFail($id);

        $this->authorize('resetPassword', $utilisateur);

        $clair = Str::password(14, symbols: false);

        $utilisateur->forceFill([
            'password'             => Hash::make($clair),
            'must_change_password' => true,
        ])->save();

        $utilisateur->closeSessions();

        AuditLog::record('mot_de_passe.reinitialise_par_admin', auth()->user(), $utilisateur, [
            'email' => $utilisateur->email,
        ]);

        $this->motDePasseAffiche = $clair;
        $this->compteAffiche = $utilisateur->username;
        $this->mdpEnvoye = false;
        $this->mdpAChanger = true;

        $this->toastSucces('Mot de passe provisoire généré. Notez-le, il ne sera plus affiché.');
    }

    public function ouvrirMotDePasse(int $id): void
    {
        $this->authorize('resetPassword', User::findOrFail($id));

        $this->reset(['nouveauMdp', 'nouveauMdp_confirmation']);
        $this->mdpPour = $id;
        $this->mdpMode = 'generer';
        $this->mdpExigerChangement = true;
        $this->resetValidation();
    }

    public function fermerMotDePasse(): void
    {
        $this->mdpPour = null;
    }

    /**
     * Mot de passe oublié par un employé : la gérance en génère un
     * provisoire (affiché une fois) ou en saisit un, et choisit s'il
     * devra être changé à la prochaine connexion.
     */
    public function appliquerMotDePasse(): void
    {
        $utilisateur = User::findOrFail($this->mdpPour);

        $this->authorize('resetPassword', $utilisateur);

        if ($this->mdpMode === 'lien') {
            $this->envoyerLienReinitialisation($utilisateur->id);
            $this->mdpPour = null;

            return;
        }

        if ($this->mdpMode === 'saisir') {
            $this->validate(
                ['nouveauMdp' => ['required', 'confirmed', PasswordRule::defaults()]],
                ['nouveauMdp.required' => __('Saisissez le nouveau mot de passe.'),
                 'nouveauMdp.confirmed' => __('Les deux mots de passe ne correspondent pas.')],
            );
        }

        $clair = $this->mdpMode === 'saisir' ? $this->nouveauMdp : Str::password(14, symbols: false);

        $utilisateur->forceFill([
            'password'             => Hash::make($clair),
            'must_change_password' => $this->mdpExigerChangement,
        ])->save();

        $utilisateur->closeSessions();

        AuditLog::record('mot_de_passe.reinitialise_par_admin', auth()->user(), $utilisateur, [
            'email' => $utilisateur->email, 'methode' => $this->mdpMode,
        ]);

        if ($this->mdpMode === 'generer') {
            $this->motDePasseAffiche = $clair;
            $this->compteAffiche = $utilisateur->username;
            $this->mdpEnvoye = false;
            $this->mdpAChanger = $this->mdpExigerChangement;
        }

        $this->reset(['mdpPour', 'nouveauMdp', 'nouveauMdp_confirmation']);

        $this->toastSucces(__('Mot de passe de :nom changé.', ['nom' => $utilisateur->name]));
    }

    public function basculerActif(int $id): void
    {
        $utilisateur = User::findOrFail($id);

        $this->authorize('toggleActive', $utilisateur);

        $utilisateur->forceFill(['is_active' => ! $utilisateur->is_active])->save();

        if (! $utilisateur->is_active) {
            $utilisateur->closeSessions();
        }

        AuditLog::record(
            $utilisateur->is_active ? 'utilisateur.reactive' : 'utilisateur.suspendu',
            auth()->user(),
            $utilisateur,
            ['email' => $utilisateur->email],
        );

        $this->toastSucces($utilisateur->is_active
            ? __('Compte de :nom réactivé.', ['nom' => $utilisateur->name])
            : __('Compte de :nom suspendu. Sa session en cours sera coupée.', ['nom' => $utilisateur->name]));
    }

    /** Lève le verrouillage posé après des échecs de connexion. */
    public function deverrouiller(int $id): void
    {
        $utilisateur = User::findOrFail($id);

        $this->authorize('update', $utilisateur);

        $utilisateur->forceFill(['failed_login_attempts' => 0, 'locked_until' => null])->save();

        AuditLog::record('compte.deverrouille', auth()->user(), $utilisateur, ['identifiant' => $utilisateur->username]);

        $this->toastSucces(__('Compte de :nom déverrouillé.', ['nom' => $utilisateur->name]));
    }

    public function supprimer(int $id): void
    {
        $utilisateur = User::findOrFail($id);

        $this->authorize('delete', $utilisateur);

        AuditLog::record('utilisateur.supprime', auth()->user(), $utilisateur, [
            'email' => $utilisateur->email,
        ]);

        // Suppression logique : les bons d'entrée et de sortie conservent
        // leur auteur, la piste d'audit reste lisible.
        $utilisateur->delete();

        $this->toastSucces('Compte supprimé.');
    }

    public function masquerMotDePasse(): void
    {
        $this->motDePasseAffiche = null;
        $this->compteAffiche = null;
    }

    public function reinitialiser(): void
    {
        $this->reset([
            'enEdition', 'name', 'username', 'email', 'phone', 'password', 'password_confirmation',
        ]);

        $this->role = UserRole::Caissier->value;
        $this->is_active = true;
        $this->genererMotDePasse = true;
        $this->must_change_password = true;
        $this->droitsDuProfil();

        $this->resetValidation();
    }

    private function envoyerIdentifiants(User $utilisateur, string $motDePasse): void
    {
        try {
            $utilisateur->notify(new AccountCreatedNotification($motDePasse, auth()->user()->name));
            $this->mdpEnvoye = true;
        } catch (\Throwable $e) {
            report($e);

            $this->toastInfo(
                'Le compte est créé, mais le courriel n\'a pas pu partir. '
                .'Communiquez le mot de passe affiché ci-dessus.'
            );
        }
    }

    /** Filtres par colonne (entonnoir des en-têtes). */
    protected function filtresColonnes(): array
    {
        return [
            'utilisateur' => self::filtreTexte('name', 'username', 'email'),
            'role' => self::filtreEgal('role'),
            'connexion' => self::filtreDate('last_login_at'),
            'etat' => fn ($q, $v) => match ($v) {
                'actif'      => $q->where('is_active', true)->where(fn ($s) => $s->whereNull('locked_until')->orWhere('locked_until', '<=', now())),
                'verrouille' => $q->where('locked_until', '>', now()),
                'suspendu'   => $q->where('is_active', false),
                default      => $q,
            },
        ];
    }

    public function render(): View
    {
        $utilisateurs = User::query()
            ->when($this->recherche, fn ($q) => $q->where(function ($sub) {
                $sub->whereLikeLoose('name', $this->recherche)
                    ->orWhereLikeLoose('username', $this->recherche)
                    ->orWhereLike('email', "%{$this->recherche}%");
            }))
            ->when($this->filtreRole, fn ($q) => $q->where('role', $this->filtreRole))
            ->tap(fn ($q) => $this->appliquerFiltres($q))
            ->orderByRaw("CASE role WHEN 'admin' THEN 1 WHEN 'gerant' THEN 2 WHEN 'magasinier' THEN 3 ELSE 4 END")
            ->orderBy('name')
            ->paginate(20);

        return view('livewire.users.user-manager', [
            'utilisateurs'  => $utilisateurs,
            'roles'         => UserRole::cases(),
            'attribuables'  => auth()->user()->role->assignableRoles(),
            'gerance'       => UserRole::tryFrom($this->role)?->isManagement() ?? false,
            'cible'         => $this->mdpPour ? User::find($this->mdpPour) : null,
            // Tableau « qui voit quoi » : même règle que le menu et les routes.
            'menus'         => [
                'tableau-de-bord' => __('Tableau de bord'), 'caisse' => __('Caisse'), 'alertes' => __('Alertes'),
                'produits' => __('Produits'), 'categories' => __('Catégories'), 'fournisseurs' => __('Fournisseurs'),
                'entrees' => __('Entrées'), 'sorties' => __('Sorties'), 'lots' => __('Lots'),
                'peremptions' => __('Péremptions'), 'mouvements' => __('Mouvements'), 'inventaires' => __('Inventaires'),
                'rapports' => __('Rapports'), 'comptabilite' => __('Comptabilité'), 'utilisateurs' => __('Utilisateurs et accès'),
                'parametres' => __('Paramètres'), 'journal' => __('Journal de sécurité'),
            ],
        ]);
    }
}
