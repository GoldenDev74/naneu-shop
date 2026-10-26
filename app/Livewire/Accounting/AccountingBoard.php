<?php

namespace App\Livewire\Accounting;

use App\Enums\UserRole;
use App\Livewire\Concerns\InteractsWithToasts;
use App\Livewire\Concerns\WithColumnFilters;
use App\Models\AuditLog;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\StockEntry;
use App\Services\AccountingService;
use App\Support\Money;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Comptabilité : charges complémentaires (transport, vignettes,
 * électricité / eau…) et bénéfice net de la période.
 */
#[Layout('layouts.app')]
#[Title('Comptabilité')]
class AccountingBoard extends Component
{
    use InteractsWithToasts, WithPagination, WithColumnFilters;

    #[Url(except: '')]
    public string $depuis = '';

    #[Url(except: '')]
    public string $jusqua = '';

    #[Url(as: 'poste', except: '')]
    public string $categorie = '';

    // --- Saisie d'une charge (fenêtre) ---------------------------------
    public bool $formulaire = false;
    public ?int $enEdition = null;
    public ?int $expense_category_id = null;
    public string $expense_date = '';
    public string $label = '';
    public string $amount = '';
    public ?int $stock_entry_id = null;
    public string $note = '';

    // --- Postes de charges (fenêtre) -----------------------------------
    public bool $postes = false;
    public string $nouveauPoste = '';

    /** @var array<int, string> noms modifiables, par id */
    public array $nomsPostes = [];

    public function mount(): void
    {
        abort_unless(auth()->user()->hasAtLeast(UserRole::Gerant), 403);

        $this->depuis = $this->depuis ?: now()->startOfMonth()->toDateString();
        $this->jusqua = $this->jusqua ?: now()->toDateString();
    }

    public function updated(string $propriete): void
    {
        if (in_array($propriete, ['depuis', 'jusqua', 'categorie'], true)) {
            $this->resetPage();
        }
    }

    public function periode(string $raccourci): void
    {
        [$this->depuis, $this->jusqua] = match ($raccourci) {
            'mois'         => [now()->startOfMonth()->toDateString(), now()->toDateString()],
            'mois_dernier' => [
                now()->subMonthNoOverflow()->startOfMonth()->toDateString(),
                now()->subMonthNoOverflow()->endOfMonth()->toDateString(),
            ],
            'trimestre'    => [now()->startOfQuarter()->toDateString(), now()->toDateString()],
            'annee'        => [now()->startOfYear()->toDateString(), now()->toDateString()],
            default        => [$this->depuis, $this->jusqua],
        };

        $this->resetPage();
    }

    /** Clic sur une ligne du tableau mensuel : la période devient ce mois. */
    public function choisirMois(string $debut, string $fin): void
    {
        [$this->depuis, $this->jusqua] = [$debut, min($fin, now()->toDateString())];
        $this->resetPage();
    }

    public function filtrerPoste(?int $id): void
    {
        $this->categorie = $id && (string) $id !== $this->categorie ? (string) $id : '';
        $this->resetPage();
    }

    // ------------------------------------------------------------------
    // Charges
    // ------------------------------------------------------------------

    public function nouvelle(?int $categorieId = null): void
    {
        $this->resetValidation();
        $this->reset(['enEdition', 'label', 'amount', 'stock_entry_id', 'note']);
        $this->expense_category_id = $categorieId ?: ($this->categorie !== '' ? (int) $this->categorie : null);
        $this->expense_date = now()->toDateString();
        $this->formulaire = true;
    }

    public function editer(int $id): void
    {
        $charge = Expense::findOrFail($id);

        $this->resetValidation();
        $this->enEdition = $charge->id;
        $this->expense_category_id = $charge->expense_category_id;
        $this->expense_date = $charge->expense_date->toDateString();
        $this->label = $charge->label;
        $this->amount = Money::number($charge->amount);
        $this->stock_entry_id = $charge->stock_entry_id;
        $this->note = (string) $charge->note;
        $this->formulaire = true;
    }

    public function fermerFormulaire(): void
    {
        $this->formulaire = false;
    }

    /** @return array<string, mixed> */
    protected function rules(): array
    {
        return [
            'expense_category_id' => ['required', 'exists:expense_categories,id'],
            'expense_date'        => ['required', 'date', 'before_or_equal:today'],
            'label'               => ['required', 'string', 'max:150'],
            'amount'              => ['required'],
            'stock_entry_id'      => ['nullable', 'exists:stock_entries,id'],
            'note'                => ['nullable', 'string', 'max:1000'],
        ];
    }

    /** @return array<string, string> */
    protected function messages(): array
    {
        return [
            'expense_category_id.required' => __('Choisissez le poste de la charge.'),
            'label.required'               => __('Indiquez ce qui a été payé.'),
            'amount.required'              => __('Indiquez le montant payé.'),
            'expense_date.before_or_equal' => __('La date ne peut pas être dans le futur.'),
        ];
    }

    public function enregistrer(): void
    {
        $donnees = $this->validate();
        $montant = Money::parse($this->amount);

        if ($montant <= 0) {
            $this->addError('amount', __('Le montant doit être supérieur à zéro.'));

            return;
        }

        $donnees['amount'] = $montant;
        $donnees['note'] = $donnees['note'] ?: null;

        if ($this->enEdition) {
            $charge = Expense::findOrFail($this->enEdition);
            $avant = $charge->only(['expense_category_id', 'expense_date', 'label', 'amount']);
            $charge->update($donnees);

            AuditLog::record('charge.modifiee', auth()->user(), $charge, [
                'avant' => $avant, 'apres' => $charge->only(array_keys($avant)),
            ]);

            $this->toastSucces(__('Charge :ref mise à jour.', ['ref' => $charge->reference]));
        } else {
            $charge = DB::transaction(fn () => Expense::create($donnees + [
                'reference' => Expense::nextReference(),
                'user_id'   => auth()->id(),
            ]));

            AuditLog::record('charge.creee', auth()->user(), $charge, [
                'montant' => $charge->amount, 'poste' => $charge->category?->name,
            ]);

            $this->toastSucces(__('Charge :ref enregistrée — :montant.', [
                'ref' => $charge->reference, 'montant' => Money::format($charge->amount),
            ]));
        }

        $this->formulaire = false;
    }

    public function supprimer(int $id): void
    {
        $charge = Expense::findOrFail($id);
        $charge->delete();

        AuditLog::record('charge.supprimee', auth()->user(), $charge, [
            'montant' => $charge->amount, 'libelle' => $charge->label,
        ]);

        $this->toastSucces(__('Charge :ref supprimée.', ['ref' => $charge->reference]));
    }

    // ------------------------------------------------------------------
    // Postes de charges
    // ------------------------------------------------------------------

    public function ouvrirPostes(): void
    {
        $this->nomsPostes = ExpenseCategory::orderBy('id')->pluck('name', 'id')->all();
        $this->nouveauPoste = '';
        $this->resetValidation();
        $this->postes = true;
    }

    public function fermerPostes(): void
    {
        $this->postes = false;
    }

    public function ajouterPoste(): void
    {
        $this->validate(['nouveauPoste' => ['required', 'string', 'max:80', 'unique:expense_categories,name']], [
            'nouveauPoste.required' => __('Donnez un nom au poste.'),
            'nouveauPoste.unique'   => __('Ce poste existe déjà.'),
        ]);

        $poste = ExpenseCategory::create(['name' => trim($this->nouveauPoste)]);
        $this->nomsPostes[$poste->id] = $poste->name;
        $this->nouveauPoste = '';

        $this->toastSucces(__('Poste « :nom » ajouté.', ['nom' => $poste->name]));
    }

    public function renommerPoste(int $id): void
    {
        $nom = trim($this->nomsPostes[$id] ?? '');

        if ($nom === '') {
            $this->addError("nomsPostes.{$id}", __('Donnez un nom au poste.'));

            return;
        }

        ExpenseCategory::findOrFail($id)->update(['name' => $nom]);
        $this->toastSucces(__('Poste renommé.'));
    }

    public function basculerPoste(int $id): void
    {
        $poste = ExpenseCategory::findOrFail($id);
        $poste->update(['is_active' => ! $poste->is_active]);

        $this->toastSucces($poste->is_active ? __('Poste réactivé.') : __('Poste masqué : ses charges passées restent comptées.'));
    }

    /**
     * Bons d'entrée récents auxquels rattacher un transport, plus celui
     * déjà lié à la charge modifiée s'il est plus ancien.
     *
     * @return \Illuminate\Support\Collection<int, StockEntry>
     */
    private function entreesProposees(): \Illuminate\Support\Collection
    {
        $entrees = StockEntry::query()->validated()->with('supplier:id,name')
            ->latest('entry_date')->latest('id')->limit(40)->get();

        if ($this->stock_entry_id && ! $entrees->contains('id', $this->stock_entry_id)) {
            $entrees->push(StockEntry::with('supplier:id,name')->find($this->stock_entry_id));
        }

        return $entrees->filter()->values();
    }

    /** Filtres par colonne (entonnoir des en-têtes). */
    protected function filtresColonnes(): array
    {
        return [
            'date' => self::filtreDate('expense_date'),
            'poste' => self::filtreEgal('expense_category_id'),
            'libelle' => self::filtreTexte('label', 'note'),
            'montant' => self::filtreNombre('amount', montant: true),
            'auteur' => self::filtreRelation('user', 'name'),
        ];
    }

    public function render(AccountingService $compta): View
    {
        $charges = Expense::query()
            ->with(['category', 'stockEntry:id,reference', 'user:id,name'])
            ->between($this->depuis, $this->jusqua)
            ->when($this->categorie !== '', fn (Builder $q) => $q->where('expense_category_id', $this->categorie))
            ->tap(fn ($q) => $this->appliquerFiltres($q))
            ->latest('expense_date')
            ->latest('id')
            ->paginate(15);

        return view('livewire.accounting.accounting-board', [
            'resultat'    => $compta->profitAndLoss($this->depuis, $this->jusqua),
            'mensuel'     => $compta->monthlyResults(6),
            'charges'     => $charges,
            'categories'  => ExpenseCategory::orderBy('name')->get(),
            'entrees'     => $this->formulaire ? $this->entreesProposees() : collect(),
        ]);
    }
}
