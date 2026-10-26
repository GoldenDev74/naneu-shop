<?php

namespace App\Livewire\Suppliers;

use App\Enums\UserRole;
use App\Livewire\Concerns\InteractsWithToasts;
use App\Livewire\Concerns\WithColumnFilters;
use App\Models\Supplier;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Fournisseurs')]
class SupplierManager extends Component
{
    use InteractsWithToasts, WithPagination, WithColumnFilters;

    #[Url(as: 'q', except: '')]
    public string $recherche = '';

    public bool $panneau = false;
    public ?int $enEdition = null;

    public string $name = '';
    public string $contact_name = '';
    public string $phone = '';
    public string $email = '';
    public string $address = '';
    public string $city = 'Lomé';
    public bool $is_local = true;
    public string $notes = '';
    public bool $is_active = true;

    public function mount(): void
    {
        abort_unless(auth()->user()->canAccess('fournisseurs'), 403);
    }

    /** Lecture seule : consultation, sans création, modification ni suppression. */
    private function peutModifier(): bool
    {
        return auth()->user()->canWrite('fournisseurs');
    }

    public function updatedRecherche(): void
    {
        $this->resetPage();
    }

    /** @return array<string, mixed> */
    protected function rules(): array
    {
        return [
            'name'         => ['required', 'string', 'max:150'],
            'contact_name' => ['nullable', 'string', 'max:120'],
            // Numéros togolais : +228 XX XX XX XX, souvent notés sans indicatif.
            'phone'        => ['nullable', 'string', 'regex:/^[0-9+\s().-]{6,25}$/'],
            'email'        => ['nullable', 'email', 'max:150'],
            'address'      => ['nullable', 'string', 'max:255'],
            'city'         => ['nullable', 'string', 'max:100'],
            'is_local'     => ['boolean'],
            'notes'        => ['nullable', 'string', 'max:1000'],
            'is_active'    => ['boolean'],
        ];
    }

    /** @return array<string, string> */
    protected function messages(): array
    {
        return [
            'name.required' => 'Le nom du fournisseur est obligatoire.',
            'phone.regex'   => 'Numéro de téléphone invalide.',
            'email.email'   => 'Adresse e-mail invalide.',
        ];
    }

    public function nouveau(): void
    {
        abort_unless($this->peutModifier(), 403);

        $this->reinitialiser();
        $this->panneau = true;
    }

    public function editer(int $id): void
    {
        abort_unless($this->peutModifier(), 403);

        $fournisseur = Supplier::findOrFail($id);

        $this->enEdition = $fournisseur->id;

        $this->fill([
            'name'         => $fournisseur->name,
            'contact_name' => (string) $fournisseur->contact_name,
            'phone'        => (string) $fournisseur->phone,
            'email'        => (string) $fournisseur->email,
            'address'      => (string) $fournisseur->address,
            'city'         => (string) $fournisseur->city,
            'is_local'     => $fournisseur->is_local,
            'notes'        => (string) $fournisseur->notes,
            'is_active'    => $fournisseur->is_active,
        ]);

        $this->panneau = true;
    }

    public function enregistrer(): void
    {
        abort_unless($this->peutModifier(), 403);

        $donnees = $this->validate();

        $fournisseur = $this->enEdition ? Supplier::findOrFail($this->enEdition) : new Supplier;

        $fournisseur->fill($donnees)->save();

        $this->toastSucces($this->enEdition ? 'Fournisseur mis à jour.' : 'Fournisseur créé.');

        $this->reinitialiser();
        $this->panneau = false;
    }

    public function basculerActif(int $id): void
    {
        abort_unless($this->peutModifier(), 403);

        $fournisseur = Supplier::findOrFail($id);

        $fournisseur->forceFill(['is_active' => ! $fournisseur->is_active])->save();

        $this->toastSucces($fournisseur->is_active ? 'Fournisseur réactivé.' : 'Fournisseur désactivé.');
    }

    public function reinitialiser(): void
    {
        $this->reset([
            'enEdition', 'name', 'contact_name', 'phone', 'email',
            'address', 'notes',
        ]);

        $this->city = 'Lomé';
        $this->is_local = true;
        $this->is_active = true;

        $this->resetValidation();
    }

    /** Filtres par colonne (entonnoir des en-têtes). */
    protected function filtresColonnes(): array
    {
        return [
            'fournisseur' => self::filtreTexte('name'),
            'contact' => self::filtreTexte('contact_name', 'phone'),
            'ville' => self::filtreTexte('city'),
        ];
    }

    public function render(): View
    {
        return view('livewire.suppliers.supplier-manager', [
            'peutModifier' => $this->peutModifier(),
            'fournisseurs' => Supplier::query()
                ->search($this->recherche)
                ->tap(fn ($q) => $this->appliquerFiltres($q))
                ->withCount('stockEntries')
                ->orderBy('name')
                ->paginate(20),
        ]);
    }
}
