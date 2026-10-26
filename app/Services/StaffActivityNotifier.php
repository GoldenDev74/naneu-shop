<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Product;
use App\Models\StockEntry;
use App\Models\StockExit;
use App\Models\Supplier;
use App\Models\User;
use App\Notifications\StaffActivityNotification;
use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Notification;

/**
 * Prévient la gérance (gérants et administrateurs actifs) de chaque
 * création, modification ou suppression faite par un profil Gestion de
 * stock : produits, catégories, fournisseurs, entrées et sorties.
 */
class StaffActivityNotifier
{
    /**
     * Colonnes mises à jour par l'application elle-même (stock, dernier
     * prix d'achat…) : les signaler noierait la gérance sous le bruit.
     */
    private const COLONNES_SYSTEME = [
        'quantity_on_hand', 'last_purchase_price', 'updated_at', 'created_at', 'deleted_at', 'slug',
    ];

    /** Libellés lisibles des champs modifiés ; la valeur dit s'il faut montrer avant → après. */
    private const CHAMPS = [
        'name'                => ['nom', true],
        'sale_price'          => ['prix de vente', true],
        'category_id'         => ['catégorie', true],
        'parent_id'           => ['catégorie parente', true],
        'unit_id'             => ['unité', false],
        'default_supplier_id' => ['fournisseur habituel', false],
        'barcode'             => ['code-barres', true],
        'internal_code'       => ['code interne', true],
        'sku'                 => ['référence', true],
        'alert_quantity'      => ['seuil d\'alerte', true],
        'reorder_quantity'    => ['quantité à recommander', true],
        'vat_rate'            => ['taux de TVA', true],
        'is_vat_exempt'       => ['exonération de TVA', false],
        'is_perishable'       => ['périssable', false],
        'expiry_alert_days'   => ['délai d\'alerte péremption', true],
        'shelf_life_days'     => ['durée de conservation', true],
        'image_path'          => ['photo', false],
        'description'         => ['description', false],
        'color'               => ['couleur', false],
        'phone'               => ['téléphone', true],
        'email'               => ['e-mail', true],
        'contact_name'        => ['contact', true],
        'address'             => ['adresse', false],
        'city'                => ['ville', true],
        'notes'               => ['notes', false],
        'is_local'            => ['fournisseur local', false],
        'is_quick_item'       => ['accès rapide caisse', false],
        'display_order'       => ['ordre d\'affichage', false],
    ];

    /**
     * Toute modification faite par un employé (hors gérance) est notifiée :
     * ses droits peuvent lui ouvrir n'importe quel menu d'exploitation.
     */
    public function concerne(?User $acteur): bool
    {
        return $acteur !== null && ! $acteur->role->isManagement();
    }

    public function modele(string $evenement, Model $modele, ?User $acteur): void
    {
        if (! $this->concerne($acteur) || ! ($objet = $this->objet($modele))) {
            return;
        }

        $action = $evenement;
        $changements = [];

        // Complément enregistré juste après la création (photo du produit…) :
        // la notification « a créé » suffit.
        if ($evenement === 'modifie' && $modele->wasRecentlyCreated) {
            return;
        }

        if ($evenement === 'modifie') {
            $modifies = array_diff(array_keys($modele->getChanges()), self::COLONNES_SYSTEME);

            if ($modifies === []) {
                return;
            }

            // Simple bascule actif / inactif : un verbe dédié est plus parlant.
            if ($modifies === ['is_active']) {
                $action = $modele->is_active ? 'reactive' : 'desactive';
            } else {
                $changements = collect($modifies)
                    ->reject(fn ($c) => $c === 'is_active')
                    ->map(fn (string $c) => $this->changement($modele, $c))
                    ->values()
                    ->all();

                if (in_array('is_active', $modifies, true)) {
                    $changements[] = [$modele->is_active ? 'réactivé' : 'désactivé'];
                }
            }
        }

        $this->envoyer($acteur, $action, $objet, (string) $modele->getAttribute('name'), $this->url($modele, $evenement), $changements);
    }

    public function entree(StockEntry $entree): void
    {
        if (! $this->concerne($entree->user)) {
            return;
        }

        $this->envoyer($entree->user, 'enregistre', 'entree', $entree->reference, route('entrees.show', $entree, absolute: false), [], [
            ':n produit(s), :montant', ['n' => $entree->lines->count(), 'montant' => Money::format($entree->total_cost)],
        ]);
    }

    public function sortie(StockExit $sortie): void
    {
        // Les ventes en caisse sont le travail courant de la vendeuse : on ne
        // notifie que les bons de sortie (pertes, consommation, ventes saisies).
        if (! $this->concerne($sortie->user) || $sortie->payment_method !== null) {
            return;
        }

        $this->envoyer($sortie->user, 'enregistre', 'sortie', $sortie->reference, route('sorties.show', $sortie, absolute: false), [], [
            ':motif, :n produit(s), :montant', [
                'motif'   => $sortie->type->label(),
                'n'       => $sortie->lines->count(),
                'montant' => Money::format($sortie->type->generatesRevenue() ? $sortie->total_sale : $sortie->total_cost),
            ],
        ]);
    }

    /**
     * @param  array<int, array<int, string|null>>  $changements
     * @param  array{0: string, 1?: array<string, mixed>}|null  $detail
     */
    private function envoyer(User $acteur, string $action, string $objet, string $nom, string $url, array $changements = [], ?array $detail = null): void
    {
        $destinataires = User::query()
            ->where('is_active', true)
            ->whereIn('role', [UserRole::Admin->value, UserRole::Gerant->value])
            ->whereKeyNot($acteur->id)
            ->get();

        if ($destinataires->isEmpty()) {
            return;
        }

        try {
            Notification::send($destinataires, new StaffActivityNotification(
                $acteur->name, $acteur->role->labelKey(), $action, $objet, $nom, $url, $changements, $detail,
            ));
        } catch (\Throwable $e) {
            // Une notification ratée ne doit jamais bloquer l'enregistrement.
            report($e);
        }
    }

    /** @return array<int, string|null> */
    private function changement(Model $modele, string $colonne): array
    {
        [$libelle, $avecValeurs] = self::CHAMPS[$colonne] ?? [$colonne, false];

        if (! $avecValeurs) {
            return [$libelle];
        }

        return [$libelle, $this->valeur($modele, $colonne, $modele->getOriginal($colonne)), $this->valeur($modele, $colonne, $modele->getAttribute($colonne))];
    }

    private function valeur(Model $modele, string $colonne, mixed $valeur): ?string
    {
        if ($valeur === null || $valeur === '') {
            return null;
        }

        return match ($colonne) {
            'sale_price'               => Money::format((int) $valeur),
            'category_id', 'parent_id' => Category::withTrashed()->find($valeur)?->name,
            'alert_quantity', 'reorder_quantity' => rtrim(rtrim(Money::number((float) $valeur, 3), '0'), ',.'),
            default                    => is_scalar($valeur) ? (string) $valeur : null,
        };
    }

    private function objet(Model $modele): ?string
    {
        return match (true) {
            $modele instanceof Product  => 'produit',
            $modele instanceof Category => 'categorie',
            $modele instanceof Supplier => 'fournisseur',
            default                     => null,
        };
    }

    private function url(Model $modele, string $evenement): string
    {
        return match (true) {
            $modele instanceof Product && $evenement !== 'supprime' => route('produits.edit', $modele, absolute: false),
            $modele instanceof Product  => route('produits.index', absolute: false),
            $modele instanceof Category => route('categories.index', absolute: false),
            default                     => route('fournisseurs.index', ['q' => $modele->getAttribute('name')], absolute: false),
        };
    }
}
