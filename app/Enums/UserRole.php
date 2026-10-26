<?php

namespace App\Enums;

enum UserRole: string
{
    case Admin      = 'admin';
    case Gerant     = 'gerant';
    case Magasinier = 'magasinier';
    case Caissier   = 'caissier';

    public function label(): string
    {
        return __($this->labelKey());
    }

    /** Libellé non traduit (clé de traduction), pour les textes composés plus tard. */
    public function labelKey(): string
    {
        return match ($this) {
            self::Admin      => 'Administrateur',
            self::Gerant     => 'Gérant',
            self::Magasinier => 'Gestion de stock',
            self::Caissier   => 'Vente (caisse)',
        };
    }

    public function description(): string
    {
        return __(match ($this) {
            self::Admin      => 'Accès à tous les menus, y compris les comptes administrateurs.',
            self::Gerant     => 'Accès à tous les menus. Crée les comptes des employés et reçoit leurs notifications.',
            self::Magasinier => 'Tous les menus d\'exploitation, en accès complet. Chaque modification est notifiée à la gérance.',
            self::Caissier   => 'Caisse pour vendre, et consultation des produits, catégories, fournisseurs, entrées et sorties (lecture seule).',
        });
    }

    /**
     * Niveau hiérarchique : un rôle donne accès à tout ce que permettent
     * les niveaux inférieurs.
     */
    public function level(): int
    {
        return match ($this) {
            self::Admin      => 40,
            self::Gerant     => 30,
            self::Magasinier => 20,
            self::Caissier   => 10,
        };
    }

    /**
     * Menus d'exploitation, dans l'ordre du menu latéral. Ce sont ceux que
     * la gérance attribue à ses employés, en lecture ou en accès complet.
     */
    public const SECTIONS_ATTRIBUABLES = [
        'tableau-de-bord' => 'Tableau de bord',
        'caisse'          => 'Caisse',
        'alertes'         => 'Alertes',
        'produits'        => 'Produits',
        'categories'      => 'Catégories',
        'fournisseurs'    => 'Fournisseurs',
        'entrees'         => 'Entrées',
        'sorties'         => 'Sorties',
        'lots'            => 'Lots',
        'peremptions'     => 'Péremptions',
        'mouvements'      => 'Mouvements',
        'inventaires'     => 'Inventaires',
    ];

    /** Pilotage et administration : jamais attribués, réservés à la gérance. */
    public const SECTIONS_RESERVEES_GERANCE = [
        'rapports'     => 'Rapports',
        'comptabilite' => 'Comptabilité',
        'utilisateurs' => 'Utilisateurs et accès',
        'parametres'   => 'Paramètres',
        'journal'      => 'Journal de sécurité',
    ];

    /** Lecture seule par nature : rien à créer ni modifier. */
    public const SECTIONS_CONSULTATION = ['lots', 'mouvements'];

    /** La gérance (admin, gérant) a tout, sans réglage par menu. */
    public function isManagement(): bool
    {
        return $this === self::Admin || $this === self::Gerant;
    }

    /**
     * Droits proposés à la création d'un compte de ce profil. La gérance
     * peut ensuite les ajuster menu par menu (User::$permissions).
     *
     * @return array<string, AccessLevel>
     */
    public function defaultPermissions(): array
    {
        $tout = fn (AccessLevel $niveau) => array_fill_keys(array_keys(self::SECTIONS_ATTRIBUABLES), $niveau);

        return match ($this) {
            self::Admin, self::Gerant => $tout(AccessLevel::Complet),
            self::Magasinier          => $tout(AccessLevel::Complet),
            self::Caissier            => [
                'caisse'       => AccessLevel::Complet,
                'produits'     => AccessLevel::Lecture,
                'categories'   => AccessLevel::Lecture,
                'fournisseurs' => AccessLevel::Lecture,
                'entrees'      => AccessLevel::Lecture,
                'sorties'      => AccessLevel::Lecture,
            ],
        };
    }

    /**
     * Rôles qu'un profil peut attribuer : la gérance crée les comptes de
     * ses employés, seul un administrateur crée gérants et administrateurs.
     *
     * @return array<int, self>
     */
    public function assignableRoles(): array
    {
        return match ($this) {
            self::Admin  => self::cases(),
            self::Gerant => [self::Magasinier, self::Caissier],
            default      => [],
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Admin      => 'bg-rose-100 text-rose-700 dark:bg-rose-900/40 dark:text-rose-300',
            self::Gerant     => 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300',
            self::Magasinier => 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300',
            self::Caissier   => 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $role) => [$role->value => $role->label()])
            ->all();
    }
}
