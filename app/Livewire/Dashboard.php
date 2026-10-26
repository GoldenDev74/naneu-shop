<?php

namespace App\Livewire;

use App\Enums\UserRole;
use App\Models\StockAlert;
use App\Models\StockBatch;
use App\Services\ReportService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Tableau de bord')]
class Dashboard extends Component
{
    /** Redessine le tableau dès qu'un mouvement est diffusé par Reverb. */
    #[On('stock-modifie')]
    #[On('entree-enregistree')]
    #[On('sortie-enregistree')]
    #[On('alerte-recue')]
    public function rafraichir(): void
    {
    }

    public function render(ReportService $reports): View
    {
        $utilisateur = auth()->user();
        $voitFinances = $utilisateur->hasAtLeast(UserRole::Gerant);

        return view('livewire.dashboard', [
            'chiffres'      => $reports->dashboard(),
            'voitFinances'  => $voitFinances,
            'serie'         => $voitFinances ? $reports->salesSeries(14) : collect(),
            'topProduits'   => $voitFinances
                ? $reports->topProducts(now()->startOfMonth()->toDateString(), now()->toDateString(), 5)
                : collect(),
            'alertes'       => StockAlert::query()
                ->open()
                ->bySeverity()
                ->with(['product:id,name,sku', 'batch:id,batch_number,expiry_date'])
                ->limit(8)
                ->get(),
            'lotsCritiques' => StockBatch::query()
                ->available()
                ->whereNotNull('expiry_date')
                ->whereDate('expiry_date', '<=', now()->addDays((int) config('naneu.stock.expiry_critical_days', 7)))
                ->with(['product:id,name,sku,unit_id', 'product.unit:id,symbol,allows_decimal'])
                ->orderBy('expiry_date')
                ->limit(6)
                ->get(),
            'aReapprovisionner' => $reports->reorderList()->take(6),
        ]);
    }
}
