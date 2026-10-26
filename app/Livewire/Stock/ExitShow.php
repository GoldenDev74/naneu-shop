<?php

namespace App\Livewire\Stock;

use App\Enums\MovementDirection;
use App\Enums\UserRole;
use App\Livewire\Concerns\InteractsWithToasts;
use App\Models\StockExit;
use App\Models\StockMovement;
use App\Services\StockService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class ExitShow extends Component
{
    use InteractsWithToasts;

    public StockExit $exit;

    public bool $confirmationAnnulation = false;
    public string $motifAnnulation = '';

    public function mount(StockExit $exit): void
    {
        $this->authorize('view', $exit);

        $this->exit = $exit->load(['lines.product.unit', 'user']);
    }

    public function annuler(StockService $stock): void
    {
        $this->authorize('cancel', $this->exit);

        $this->validate(
            ['motifAnnulation' => ['required', 'string', 'min:5', 'max:255']],
            ['motifAnnulation.required' => 'Indiquez le motif de l\'annulation.',
             'motifAnnulation.min'      => 'Le motif doit faire au moins 5 caractères.'],
        );

        try {
            $stock->cancelExit($this->exit, auth()->user(), $this->motifAnnulation);
        } catch (\Throwable $e) {
            $this->toastErreur($e->getMessage());
            $this->confirmationAnnulation = false;

            return;
        }

        $this->exit->refresh();
        $this->confirmationAnnulation = false;
        $this->motifAnnulation = '';

        $this->toastSucces('Bon annulé. Les quantités ont été remises dans leurs lots d\'origine.');
    }

    /**
     * Détail des lots réellement consommés, reconstruit depuis le grand
     * livre : c'est la trace exacte de ce que le FIFO a prélevé.
     *
     * @return Collection<int, StockMovement>
     */
    public function getMouvementsProperty(): Collection
    {
        return StockMovement::query()
            ->where('source_type', StockExit::class)
            ->where('source_id', $this->exit->id)
            ->where('direction', MovementDirection::Out->value)
            ->with(['batch:id,batch_number,expiry_date', 'product:id,name,sku'])
            ->orderBy('id')
            ->get();
    }

    public function render(): View
    {
        // Voir EntryShow : relations imbriquées rechargées à chaque rendu.
        $this->exit->loadMissing(['lines.product.unit', 'user']);

        return view('livewire.stock.exit-show', [
            'mouvements' => $this->mouvements->groupBy('product_id'),
            'voitPrix'   => auth()->user()->hasAtLeast(UserRole::Gerant),
        ]);
    }
}
