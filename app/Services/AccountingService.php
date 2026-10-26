<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\StockExit;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Compte de résultat simplifié de la boutique :
 *
 *   Chiffre d'affaires
 * − coût d'achat des marchandises vendues (FIFO, lots réels)
 * − TVA collectée (0 tant que la boutique n'est pas assujettie)
 * = marge brute
 * − pertes et démarque (périmés, cassés, perdus : au coût d'achat)
 * − charges complémentaires (transport, vignettes, électricité / eau…)
 * = bénéfice net
 *
 * Lecture seule : rien n'est écrit en base.
 */
class AccountingService
{
    /**
     * @return array{
     *     ventes: int, cout_ventes: int, tva: int, marge_brute: int,
     *     pertes: int, charges: int, resultat: int,
     *     par_categorie: Collection<int, array{id: int, nom: string, icone: string, couleur: string, montant: int, nombre: int}>
     * }
     */
    public function profitAndLoss(string $from, string $to): array
    {
        $ventes = StockExit::query()->validated()->sales()->between($from, $to)
            ->selectRaw('COALESCE(SUM(total_sale), 0) as ventes, COALESCE(SUM(total_cost), 0) as cout, COALESCE(SUM(total_vat), 0) as tva, COALESCE(SUM(total_margin), 0) as marge')
            ->first();

        $pertes = (int) StockExit::query()->validated()->shrinkage()->between($from, $to)->sum('total_cost');

        $parCategorie = $this->expensesByCategory($from, $to);
        $charges = (int) $parCategorie->sum('montant');
        $marge = (int) $ventes->marge;

        return [
            'ventes'        => (int) $ventes->ventes,
            'cout_ventes'   => (int) $ventes->cout,
            'tva'           => (int) $ventes->tva,
            'marge_brute'   => $marge,
            'pertes'        => $pertes,
            'charges'       => $charges,
            'resultat'      => $marge - $pertes - $charges,
            'par_categorie' => $parCategorie,
        ];
    }

    /**
     * Charges de la période, par catégorie (y compris les catégories sans
     * dépense, pour qu'un poste à zéro reste visible).
     *
     * @return Collection<int, array{id: int, nom: string, icone: string, couleur: string, montant: int, nombre: int}>
     */
    public function expensesByCategory(string $from, string $to): Collection
    {
        $sommes = Expense::query()->between($from, $to)
            ->selectRaw('expense_category_id, SUM(amount) as montant, COUNT(*) as nombre')
            ->groupBy('expense_category_id')
            ->get()
            ->keyBy('expense_category_id');

        return ExpenseCategory::query()
            ->where(fn ($q) => $q->where('is_active', true)->orWhereIn('id', $sommes->keys()))
            ->orderBy('id')
            ->get()
            ->map(fn (ExpenseCategory $c) => [
                'id'      => $c->id,
                'nom'     => $c->name,
                'icone'   => $c->icon,
                'couleur' => $c->color,
                'montant' => (int) ($sommes[$c->id]->montant ?? 0),
                'nombre'  => (int) ($sommes[$c->id]->nombre ?? 0),
            ])
            ->sortByDesc('montant')
            ->values();
    }

    /**
     * Résultat des derniers mois, du plus ancien au plus récent.
     *
     * @return Collection<int, array{mois: string, libelle: string, ventes: int, marge_brute: int, pertes: int, charges: int, resultat: int}>
     */
    public function monthlyResults(int $months = 6): Collection
    {
        return collect(range($months - 1, 0))->map(function (int $recul) {
            $debut = now()->startOfMonth()->subMonthsNoOverflow($recul);
            $fin = $debut->copy()->endOfMonth();
            $r = $this->profitAndLoss($debut->toDateString(), $fin->toDateString());

            return [
                'mois'        => $debut->format('Y-m'),
                'libelle'     => ucfirst($debut->translatedFormat('F Y')),
                'debut'       => $debut->toDateString(),
                'fin'         => $fin->toDateString(),
                'ventes'      => $r['ventes'],
                'marge_brute' => $r['marge_brute'],
                'pertes'      => $r['pertes'],
                'charges'     => $r['charges'],
                'resultat'    => $r['resultat'],
            ];
        });
    }
}
