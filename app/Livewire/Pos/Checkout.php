<?php

namespace App\Livewire\Pos;

use App\Data\ExitLineData;
use App\Enums\ExitType;
use App\Enums\MovementReason;
use App\Exceptions\InsufficientStockException;
use App\Livewire\Concerns\InteractsWithToasts;
use App\Models\Category;
use App\Models\Product;
use App\Models\StockExit;
use App\Services\StockService;
use App\Support\Money;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Caisse : chaque encaissement est une sortie « Vente » passée par le
 * StockService (FIFO/FEFO, blocage des périmés, grand livre), complétée
 * du montant remis par le client et de la monnaie rendue.
 *
 * Le prix est celui de la fiche produit : pas de remise libre au comptoir.
 */
#[Layout('layouts.app')]
#[Title('Caisse')]
class Checkout extends Component
{
    use InteractsWithToasts;

    /** @var array<int, array{product_id: int, nom: string, unite: string, decimal: bool, prix: int, quantite: string, max: float}> */
    public array $panier = [];

    public string $recherche = '';

    public ?int $categorie = null;

    /** Saisie libre du montant remis (« 10 000 », « 10000 »). */
    public string $remis = '';

    /** Dernière vente encaissée, affichée dans la fenêtre de rendu de monnaie. */
    public ?int $derniereVente = null;

    public function mount(): void
    {
        $this->authorize('createOfType', [StockExit::class, ExitType::Vente]);
    }

    // -----------------------------------------------------------------
    // Panier
    // -----------------------------------------------------------------

    public function ajouter(int $productId): void
    {
        $produit = Product::query()->active()->with('unit')->find($productId);

        if (! $produit) {
            $this->toastErreur('Produit introuvable.');

            return;
        }

        $dispo = $this->stock()->availability($produit, MovementReason::Vente, today());

        foreach ($this->panier as $i => $ligne) {
            if ($ligne['product_id'] === $produit->id) {
                $this->changerQuantite($i, $this->quantite($ligne) + 1);
                $this->recherche = '';

                return;
            }
        }

        if ($dispo['utilisable'] < 1e-6) {
            if ($dispo['perime'] > 1e-6) {
                $this->stock()->signalBlockedExpiredSale($produit, auth()->user());
                $this->toastErreur(__('« :nom » : tout le stock restant est périmé. Vente bloquée — retirez-le du rayon.', ['nom' => $produit->name]));
            } else {
                $this->toastErreur(__('« :nom » est en rupture de stock.', ['nom' => $produit->name]));
            }

            return;
        }

        $decimal = (bool) $produit->unit?->allows_decimal;

        $this->panier[] = [
            'product_id' => $produit->id,
            'nom'        => $produit->name,
            'unite'      => $produit->unit?->symbol ?? '',
            'decimal'    => $decimal,
            'prix'       => (int) $produit->sale_price,
            'quantite'   => '1',
            'max'        => (float) $dispo['utilisable'],
        ];

        $this->recherche = '';
    }

    /** Lecteur de code-barres ou touche Entrée dans la recherche. */
    public function scanner(): void
    {
        $terme = trim($this->recherche);

        if ($terme === '') {
            return;
        }

        $produit = Product::query()->active()
            ->where(fn ($q) => $q->where('barcode', $terme)->orWhere('internal_code', $terme)->orWhere('sku', $terme))
            ->first();

        if (! $produit) {
            $resultats = Product::query()->active()->search($terme)->limit(2)->get();
            $produit = $resultats->count() === 1 ? $resultats->first() : null;
        }

        if ($produit) {
            $this->ajouter($produit->id);
        }
    }

    public function plus(int $i): void
    {
        $this->changerQuantite($i, $this->quantite($this->panier[$i] ?? []) + 1);
    }

    public function moins(int $i): void
    {
        $nouvelle = $this->quantite($this->panier[$i] ?? []) - 1;

        $nouvelle <= 0 ? $this->retirer($i) : $this->changerQuantite($i, $nouvelle);
    }

    public function updatedPanier(mixed $valeur, string $cle): void
    {
        [$i, $champ] = array_pad(explode('.', $cle), 2, null);

        if ($champ === 'quantite' && isset($this->panier[(int) $i])) {
            $q = $this->quantite($this->panier[(int) $i]);

            if ($q > 0) {
                $this->changerQuantite((int) $i, $q);
            }
        }
    }

    public function retirer(int $i): void
    {
        unset($this->panier[$i]);
        $this->panier = array_values($this->panier);
    }

    public function vider(): void
    {
        $this->reset(['panier', 'remis', 'recherche']);
    }

    private function changerQuantite(int $i, float $quantite): void
    {
        if (! isset($this->panier[$i])) {
            return;
        }

        $ligne = $this->panier[$i];

        if (! $ligne['decimal']) {
            $quantite = round($quantite);
        }

        if ($quantite > $ligne['max'] + 1e-6) {
            $quantite = $ligne['max'];
            $this->toastErreur(__('Stock vendable de « :nom » : :q seulement.', [
                'nom' => $ligne['nom'],
                'q'   => $this->afficherQuantite($ligne['max']).' '.$ligne['unite'],
            ]));
        }

        $this->panier[$i]['quantite'] = $this->afficherQuantite($quantite);
    }

    private function quantite(array $ligne): float
    {
        return (float) str_replace([',', ' '], ['.', ''], (string) ($ligne['quantite'] ?? 0));
    }

    private function afficherQuantite(float $q): string
    {
        return rtrim(rtrim(number_format($q, 3, '.', ''), '0'), '.');
    }

    // -----------------------------------------------------------------
    // Montants
    // -----------------------------------------------------------------

    private function total(): int
    {
        return (int) collect($this->panier)->sum(fn (array $l) => (int) round($this->quantite($l) * $l['prix']));
    }

    private function remisMontant(): int
    {
        return Money::parse($this->remis);
    }

    private function monnaie(): int
    {
        return max(0, $this->remisMontant() - $this->total());
    }

    /** Billets et pièces courants : un clic au lieu de taper le montant. */
    public function remettre(int|string $montant): void
    {
        $this->remis = $montant === 'exact' ? (string) $this->total() : (string) ($this->remisMontant() + (int) $montant);
    }

    // -----------------------------------------------------------------
    // Encaissement
    // -----------------------------------------------------------------

    public function encaisser(StockService $stock): void
    {
        $this->authorize('createOfType', [StockExit::class, ExitType::Vente]);

        if ($this->panier === []) {
            $this->toastErreur('Le panier est vide.');

            return;
        }

        $total = $this->total();
        $remis = $this->remisMontant();

        if ($remis < $total) {
            $this->addError('remis', __('Il manque :montant.', ['montant' => Money::format($total - $remis)]));

            return;
        }

        $lignes = collect($this->panier)
            ->filter(fn (array $l) => $this->quantite($l) > 0)
            ->map(fn (array $l) => new ExitLineData($l['product_id'], $this->quantite($l), $l['prix']))
            ->values()
            ->all();

        try {
            // Pas de transaction englobante : sur un refus, issue() ouvre
            // l'alerte « vente bloquée » hors transaction, elle doit rester.
            $vente = $stock->issue(auth()->user(), ExitType::Vente, $lignes, [
                'payment_method' => 'especes',
            ]);
        } catch (InsufficientStockException $e) {
            $this->toastErreur($e->getMessage());

            return;
        }

        $vente->forceFill([
            'amount_tendered' => $remis,
            'change_given'    => max(0, $remis - $vente->total_sale),
        ])->save();

        $this->derniereVente = $vente->id;
        $this->reset(['panier', 'remis', 'recherche']);
        $this->resetErrorBag();
    }

    public function nouvelleVente(): void
    {
        $this->derniereVente = null;
        $this->dispatch('caisse-prete');
    }

    private function stock(): StockService
    {
        return app(StockService::class);
    }

    public function render(): View
    {
        $produits = Product::query()
            ->active()
            ->with('unit:id,symbol,allows_decimal')
            ->when(mb_strlen(trim($this->recherche)) >= 2, fn ($q) => $q->search(trim($this->recherche)))
            ->when($this->categorie, fn ($q) => $q->whereIn('category_id', Category::find($this->categorie)?->selfAndDescendantIds() ?? [$this->categorie]))
            ->orderByDesc('is_quick_item')
            ->orderBy('name')
            ->limit(60)
            ->get()
            ->each(function (Product $p) {
                $dispo = $this->stock()->availability($p, MovementReason::Vente, today());
                $p->setAttribute('vendable', $dispo['utilisable']);
                $p->setAttribute('perime', $dispo['perime']);
            });

        return view('livewire.pos.checkout', [
            'produits'   => $produits,
            'categories' => Category::enArbre(Category::query()->roots()->where('is_active', true)->get(['id', 'name', 'color', 'parent_id'])),
            'total'      => $this->total(),
            'monnaie'    => $this->monnaie(),
            'remisMontant' => $this->remisMontant(),
            'vente'      => $this->derniereVente ? StockExit::find($this->derniereVente) : null,
        ]);
    }
}
