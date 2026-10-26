<?php

namespace App\Livewire\Stock;

use App\Data\EntryLineData;
use App\Livewire\Concerns\InteractsWithToasts;
use App\Models\AuditLog;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Product;
use App\Models\StockEntry;
use App\Models\Supplier;
use App\Services\StockService;
use App\Support\Money;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.app')]
#[Title('Nouvelle entrée')]
class EntryForm extends Component
{
    use InteractsWithToasts, WithFileUploads;

    public string $reference = '';
    public ?int $supplier_id = null;
    public string $entry_date = '';
    public string $invoice_number = '';
    public string $note = '';
    public $document = null;

    /**
     * Frais de transport payés pour ramener la marchandise. Enregistrés en
     * charge (Comptabilité) rattachée au bon, sans toucher au coût des lots.
     */
    public string $transport = '';

    /**
     * Lignes du bon. Chaque ligne deviendra un lot distinct.
     *
     * @var array<int, array<string, mixed>>
     */
    public array $lignes = [];

    /** Champ de saisie / scan qui alimente le sélecteur de produit. */
    public string $recherche = '';

    public function mount(): void
    {
        $this->authorize('create', StockEntry::class);

        $this->reference = StockEntry::nextReference();
        $this->entry_date = now()->toDateString();
    }

    // -----------------------------------------------------------------
    // Gestion des lignes
    // -----------------------------------------------------------------

    /**
     * Ajoute un produit au bon. Si le produit y figure déjà avec la même
     * date de péremption, on incrémente la ligne existante plutôt que
     * d'en créer une seconde : deux lots identiques n'ont aucun intérêt.
     */
    public function ajouterProduit(int $productId): void
    {
        $produit = Product::with('unit')->find($productId);

        if (! $produit) {
            $this->toastErreur('Produit introuvable.');

            return;
        }

        foreach ($this->lignes as $index => $ligne) {
            if ((int) $ligne['product_id'] === $produit->id && blank($ligne['expiry_date'])) {
                $this->lignes[$index]['quantity'] = (float) $ligne['quantity'] + 1;
                $this->recherche = '';

                return;
            }
        }

        $this->lignes[] = [
            'product_id'     => $produit->id,
            'nom'            => $produit->name,
            'sku'            => $produit->sku,
            'unite'          => $produit->unit?->symbol ?? '',
            'decimal'        => (bool) $produit->unit?->allows_decimal,
            'perissable'     => $produit->is_perishable,
            'conservation'   => $produit->is_perishable ? $produit->shelf_life_days : null,
            'quantity'       => 1,
            'purchase_price' => (string) $produit->last_purchase_price,
            'expiry_date'    => '',
            'batch_number'   => '',
        ];

        $this->recherche = '';
    }

    /**
     * Saisie au clavier ou scan douchette. Une correspondance exacte de
     * code-barres ajoute directement la ligne, sans passer par la liste :
     * c'est ce qui permet d'enchaîner les scans à la réception.
     */
    public function scanner(): void
    {
        $terme = trim($this->recherche);

        if ($terme === '') {
            return;
        }

        $produit = Product::query()
            ->active()
            ->where(fn ($q) => $q->where('barcode', $terme)->orWhere('internal_code', $terme))
            ->first();

        if ($produit) {
            $this->ajouterProduit($produit->id);

            return;
        }

        $correspondances = Product::query()->active()->search($terme)->limit(2)->get();

        if ($correspondances->count() === 1) {
            $this->ajouterProduit($correspondances->first()->id);
        }
    }

    public function retirerLigne(int $index): void
    {
        unset($this->lignes[$index]);

        $this->lignes = array_values($this->lignes);
    }

    // -----------------------------------------------------------------
    // Totaux
    // -----------------------------------------------------------------

    public function getTotalProperty(): int
    {
        return collect($this->lignes)->sum(function (array $ligne) {
            $quantite = (float) str_replace(',', '.', (string) $ligne['quantity']);

            return (int) round($quantite * Money::parse($ligne['purchase_price']));
        });
    }

    // -----------------------------------------------------------------
    // Validation et enregistrement
    // -----------------------------------------------------------------

    /** @return array<string, mixed> */
    protected function rules(): array
    {
        return [
            'supplier_id'    => ['nullable', 'exists:suppliers,id'],
            'entry_date'     => ['required', 'date', 'before_or_equal:today'],
            'invoice_number' => ['nullable', 'string', 'max:60'],
            'note'           => ['nullable', 'string', 'max:1000'],
            'transport'      => ['nullable', 'string', 'max:20'],
            'document'       => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],

            'lignes'                   => ['required', 'array', 'min:1'],
            'lignes.*.product_id'      => ['required', 'exists:products,id'],
            'lignes.*.quantity'        => ['required', 'numeric', 'gt:0'],
            'lignes.*.purchase_price'  => ['required'],
            // Une date de péremption antérieure à la réception signale
            // presque toujours une faute de frappe ; on la refuse. On compare
            // à la date de réception (et non à aujourd'hui) pour pouvoir
            // saisir l'historique réel d'une réception antidatée.
            'lignes.*.expiry_date'     => ['nullable', 'date', 'after:entry_date'],
            'lignes.*.batch_number'    => ['nullable', 'string', 'max:60'],
        ];
    }

    /** @return array<string, string> */
    protected function messages(): array
    {
        return [
            'lignes.required'              => 'Ajoutez au moins un produit au bon d\'entrée.',
            'lignes.min'                   => 'Ajoutez au moins un produit au bon d\'entrée.',
            'lignes.*.quantity.gt'         => 'La quantité doit être supérieure à zéro.',
            'lignes.*.expiry_date.after'   => 'La date de péremption doit être postérieure à la date de réception.',
            'entry_date.before_or_equal'   => 'La date de réception ne peut pas être dans le futur.',
        ];
    }

    public function enregistrer(StockService $stock)
    {
        $this->authorize('create', StockEntry::class);

        $this->validate();

        // Un produit périssable doit finir avec une date limite : imprimée,
        // ou calculée depuis sa durée de conservation. Sinon il échappe à
        // tout le suivi des péremptions.
        foreach ($this->lignes as $index => $ligne) {
            if (($ligne['perissable'] ?? false) && blank($ligne['expiry_date']) && empty($ligne['conservation'])) {
                $this->addError("lignes.{$index}.expiry_date", __('Date limite requise (ou renseignez la durée de conservation du produit).'));
            }

            // Un lot à coût nul fausserait toute la marge FIFO : le prix
            // d'achat doit être un vrai montant, strictement positif.
            $prix = trim((string) $ligne['purchase_price']);

            if (! preg_match('/^\d[\d\s.,]*$/u', $prix) || Money::parse($prix) <= 0) {
                $this->addError("lignes.{$index}.purchase_price", __('Prix d\'achat invalide : saisissez un montant supérieur à zéro.'));
            }
        }

        if (filled($this->transport) && Money::parse($this->transport) <= 0) {
            $this->addError('transport', __('Montant de transport invalide.'));
        }

        if ($this->getErrorBag()->isNotEmpty()) {
            return null;
        }

        $lignes = collect($this->lignes)
            ->map(fn (array $ligne) => EntryLineData::fromArray($ligne))
            ->all();

        try {
            $chemin = null;

            if ($this->document) {
                $chemin = $this->document->storePublicly(
                    'documents/entrees',
                    ['disk' => config('naneu.storage.disk')],
                );
            }

            $entree = $stock->receive(auth()->user(), $lignes, [
                'supplier_id'    => $this->supplier_id,
                'entry_date'     => $this->entry_date,
                'invoice_number' => $this->invoice_number ?: null,
                'note'           => $this->note ?: null,
                'document_path'  => $chemin,
            ]);
        } catch (\Throwable $e) {
            report($e);

            $this->toastErreur($e->getMessage());

            return null;
        }

        $this->enregistrerTransport($entree);

        session()->flash('succes', __('Entrée :reference enregistrée — :montant.', [
            'reference' => $entree->reference,
            'montant'   => Money::format($entree->total_cost),
        ]));

        return $this->redirectRoute('entrees.show', ['entry' => $entree], navigate: true);
    }

    private function enregistrerTransport(StockEntry $entree): void
    {
        $montant = Money::parse($this->transport);
        $poste = ExpenseCategory::transport();

        if ($montant <= 0 || ! $poste) {
            return;
        }

        $charge = DB::transaction(fn () => Expense::create([
            'reference'           => Expense::nextReference(),
            'expense_category_id' => $poste->id,
            'expense_date'        => $entree->entry_date,
            'label'               => __('Transport de la marchandise').($entree->supplier ? ' — '.$entree->supplier->name : ''),
            'amount'              => $montant,
            'stock_entry_id'      => $entree->id,
            'user_id'             => auth()->id(),
        ]));

        AuditLog::record('charge.creee', auth()->user(), $charge, [
            'montant' => $montant, 'poste' => $poste->name, 'bon' => $entree->reference,
        ]);
    }

    /** @return Collection<int, Product> */
    public function getResultatsProperty(): Collection
    {
        if (mb_strlen(trim($this->recherche)) < 2) {
            return collect();
        }

        $dejaPris = collect($this->lignes)->pluck('product_id')->all();

        return Product::query()
            ->active()
            ->search($this->recherche)
            ->with('unit:id,symbol')
            ->orderByRaw('CASE WHEN id IN ('.($dejaPris ? implode(',', array_map('intval', $dejaPris)) : '0').') THEN 1 ELSE 0 END')
            ->limit(8)
            ->get();
    }

    public function render(): View
    {
        return view('livewire.stock.entry-form', [
            'fournisseurs' => Supplier::query()->active()->orderBy('name')->get(['id', 'name']),
            'resultats'    => $this->resultats,
            'total'        => $this->total,
        ]);
    }
}
