<?php

namespace App\Livewire\Expiry;

use App\Enums\UserRole;
use App\Livewire\Concerns\InteractsWithToasts;
use App\Models\Product;
use App\Services\ExpiryAdvisor;
use App\Services\StockService;
use App\Support\Money;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Vue dédiée aux dates de péremption — l'écran le plus regardé dans une
 * alimentation générale, puisque c'est là que se joue la marge perdue.
 */
#[Layout('layouts.app')]
#[Title('Péremptions')]
class ExpiryBoard extends Component
{
    use InteractsWithToasts;

    public bool $confirmationSortie = false;

    public function sortirPerimes(StockService $stock): void
    {
        abort_unless(auth()->user()->canWrite('peremptions'), 403);

        $resultat = $stock->writeOffExpired(auth()->user());

        $this->confirmationSortie = false;

        if ($resultat['lots'] === 0) {
            $this->toastInfo('Aucun lot périmé à sortir.');

            return;
        }

        $this->toastSucces(__(':n lot(s) périmé(s) sorti(s) du stock — perte de :montant enregistrée.', [
            'n'       => $resultat['lots'],
            'montant' => Money::format($resultat['valeur']),
        ]));
    }

    /** Durées de conservation saisies depuis la section « sans date ». */
    public array $conservations = [];

    /** Retire du rayon les lots périmés d'un seul produit. */
    public function sortirProduit(int $productId, StockService $stock): void
    {
        abort_unless(auth()->user()->canWrite('peremptions'), 403);

        $produit = Product::findOrFail($productId);
        $resultat = $stock->writeOffExpired(auth()->user(), $produit);

        if ($resultat['lots'] === 0) {
            $this->toastInfo('Aucun lot périmé à sortir.');

            return;
        }

        $this->toastSucces(__('« :nom » retiré du stock — perte de :montant enregistrée.', [
            'nom'     => $produit->name,
            'montant' => Money::format($resultat['valeur']),
        ]));
    }

    /**
     * La gérante indique combien de jours se garde un produit frais : ses
     * lots sans date sont datés aussitôt, les prochaines réceptions aussi.
     */
    public function definirConservation(int $productId, StockService $stock): void
    {
        abort_unless(auth()->user()->hasAtLeast(UserRole::Gerant), 403);

        $jours = (int) ($this->conservations[$productId] ?? 0);

        if ($jours < 1 || $jours > 730) {
            $this->addError("conservations.{$productId}", __('Entre 1 et 730 jours.'));

            return;
        }

        $produit = Product::findOrFail($productId);
        $produit->forceFill(['shelf_life_days' => $jours])->save();

        $n = $stock->applyShelfLife($produit);
        unset($this->conservations[$productId]);

        $this->toastSucces(__('« :nom » se conserve :j jours — :n lot(s) daté(s).', ['nom' => $produit->name, 'j' => $jours, 'n' => $n]));
    }

    public function render(ExpiryAdvisor $conseiller): View
    {
        $analyse = $conseiller->analyse();

        return view('livewire.expiry.expiry-board', [
            'analyse'       => $analyse,
            'perimesParProduit' => $analyse['perimes']->groupBy(fn ($l) => $l['lot']->product_id),
            'valeurPerimee' => $analyse['perimes']->sum('valeur'),
            'valeurRisque'  => $analyse['a_risque']->sum('valeur_risque'),
            'sansDateParProduit' => $analyse['sans_date']->groupBy(fn ($l) => $l['lot']->product_id),
            'voitPrix'      => auth()->user()->hasAtLeast(UserRole::Gerant),
            'peutSortir'    => auth()->user()->canWrite('peremptions'),
        ]);
    }
}
