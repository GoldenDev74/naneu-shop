<?php

namespace App\Livewire\Stock;

use App\Enums\UserRole;
use App\Livewire\Concerns\InteractsWithToasts;
use App\Models\StockEntry;
use App\Services\StockService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class EntryShow extends Component
{
    use InteractsWithToasts;

    public StockEntry $entry;

    public bool $confirmationAnnulation = false;
    public string $motifAnnulation = '';

    public function mount(StockEntry $entry): void
    {
        $this->authorize('view', $entry);

        $this->entry = $entry->load(['lines.product.unit', 'lines.batch', 'supplier', 'user']);
    }

    public function annuler(StockService $stock): void
    {
        $this->authorize('cancel', $this->entry);

        $this->validate(
            ['motifAnnulation' => ['required', 'string', 'min:5', 'max:255']],
            ['motifAnnulation.required' => 'Indiquez le motif de l\'annulation.',
             'motifAnnulation.min'      => 'Le motif doit faire au moins 5 caractères.'],
        );

        try {
            $stock->cancelEntry($this->entry, auth()->user(), $this->motifAnnulation);
        } catch (\Throwable $e) {
            $this->toastErreur($e->getMessage());
            $this->confirmationAnnulation = false;

            return;
        }

        $this->entry->refresh();
        $this->confirmationAnnulation = false;
        $this->motifAnnulation = '';

        $this->toastSucces('Bon annulé. Les quantités ont été retirées du stock.');
    }

    public function getDocumentUrlProperty(): ?string
    {
        if (blank($this->entry->document_path)) {
            return null;
        }

        try {
            return Storage::disk(config('naneu.storage.signing_disk'))->temporaryUrl(
                $this->entry->document_path,
                now()->addMinutes((int) config('naneu.storage.temp_url_ttl', 15)),
            );
        } catch (\Throwable) {
            return null;
        }
    }

    public function render(): View
    {
        // Entre deux requêtes, Livewire ne restaure pas toujours les relations
        // imbriquées : sans ce rechargement, le chargement paresseux (interdit)
        // faisait planter l'écran dès la première action.
        $this->entry->loadMissing(['lines.product.unit', 'lines.batch', 'supplier', 'user']);

        return view('livewire.stock.entry-show', [
            'documentUrl' => $this->documentUrl,
            'frais'       => $this->entry->expenses()->with('category')->orderBy('expense_date')->get(),
            'voitCompta'  => auth()->user()->hasAtLeast(UserRole::Gerant),
        ]);
    }
}
