<?php

namespace App\Livewire\Categories;

use App\Enums\UserRole;
use App\Livewire\Concerns\InteractsWithToasts;
use App\Livewire\Concerns\WithColumnFilters;
use App\Models\Category;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Catégories')]
class CategoryManager extends Component
{
    use InteractsWithToasts, WithColumnFilters;

    public ?int $enEdition = null;

    public string $name = '';
    public ?int $parent_id = null;
    public string $color = '#56c389';
    public string $description = '';
    public bool $is_active = true;

    public function mount(): void
    {
        abort_unless(auth()->user()->canAccess('categories'), 403);
    }

    /** Lecture seule : consultation, sans création, modification ni suppression. */
    private function peutModifier(): bool
    {
        return auth()->user()->canWrite('categories');
    }

    /** @return array<string, mixed> */
    protected function rules(): array
    {
        return [
            'name' => [
                'required', 'string', 'max:120',
                Rule::unique('categories', 'name')->ignore($this->enEdition)->withoutTrashed(),
            ],
            'parent_id'   => ['nullable', 'exists:categories,id'],
            'color'       => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'description' => ['nullable', 'string', 'max:500'],
            'is_active'   => ['boolean'],
        ];
    }

    /** @return array<string, string> */
    protected function messages(): array
    {
        return [
            'name.required' => 'Le nom de la catégorie est obligatoire.',
            'name.unique'   => 'Cette catégorie existe déjà.',
            'color.regex'   => 'La couleur doit être au format hexadécimal (#0f766e).',
        ];
    }

    public function editer(int $id): void
    {
        abort_unless($this->peutModifier(), 403);

        $categorie = Category::findOrFail($id);

        $this->enEdition = $categorie->id;

        $this->fill([
            'name'        => $categorie->name,
            'parent_id'   => $categorie->parent_id,
            'color'       => $categorie->color,
            'description' => (string) $categorie->description,
            'is_active'   => $categorie->is_active,
        ]);
    }

    public function annuler(): void
    {
        $this->reset(['enEdition', 'name', 'parent_id', 'description', 'is_active']);
        $this->color = '#56c389';
        $this->resetValidation();
    }

    public function enregistrer(): void
    {
        abort_unless($this->peutModifier(), 403);

        $donnees = $this->validate();

        // Une catégorie ne peut pas être son propre parent, ni descendre
        // d'elle-même : la hiérarchie deviendrait une boucle infinie à
        // l'affichage.
        if ($this->enEdition && $this->parent_id) {
            if ($this->parent_id === $this->enEdition || $this->descendDe($this->parent_id, $this->enEdition)) {
                $this->addError('parent_id', 'Une catégorie ne peut pas être rattachée à elle-même ou à l\'une de ses sous-catégories.');

                return;
            }
        }

        $categorie = $this->enEdition ? Category::findOrFail($this->enEdition) : new Category;

        $categorie->fill($donnees)->save();

        $this->toastSucces($this->enEdition ? 'Catégorie mise à jour.' : 'Catégorie créée.');

        $this->annuler();
    }

    /**
     * Exonère (ou soumet) d'un coup tous les produits d'un rayon et de ses
     * sous-rayons : les produits vivriers non transformés sont souvent hors
     * TVA, les saisir un par un serait fastidieux.
     */
    public function tvaRayon(int $id, bool $exonere): void
    {
        abort_unless(auth()->user()->hasAtLeast(UserRole::Gerant), 403);

        $categorie = Category::findOrFail($id);
        $n = \App\Models\Product::query()
            ->whereIn('category_id', $categorie->selfAndDescendantIds())
            ->update(['is_vat_exempt' => $exonere, 'updated_at' => now()]);

        \App\Models\AuditLog::record(
            $exonere ? 'categorie.tva_exoneree' : 'categorie.tva_appliquee',
            auth()->user(),
            $categorie,
            ['produits' => $n],
        );

        $this->toastSucces($exonere
            ? __(':n produit(s) de « :nom » exonérés de TVA.', ['n' => $n, 'nom' => $categorie->name])
            : __(':n produit(s) de « :nom » soumis à la TVA.', ['n' => $n, 'nom' => $categorie->name]));
    }

    public function supprimer(int $id): void
    {
        abort_unless($this->peutModifier(), 403);

        $categorie = Category::withCount(['products', 'children'])->findOrFail($id);

        if ($categorie->products_count > 0) {
            $this->toastErreur(
                __('« :nom » contient :n produit(s). Déplacez-les avant de supprimer la catégorie.', [
                    'nom' => $categorie->name,
                    'n'   => $categorie->products_count,
                ])
            );

            return;
        }

        if ($categorie->children_count > 0) {
            $this->toastErreur(__('« :nom » contient des sous-catégories.', ['nom' => $categorie->name]));

            return;
        }

        $categorie->delete();

        $this->toastSucces('Catégorie supprimée.');
    }

    private function descendDe(int $candidat, int $ancetre): bool
    {
        $courant = Category::find($candidat);

        while ($courant?->parent_id) {
            if ($courant->parent_id === $ancetre) {
                return true;
            }

            $courant = Category::find($courant->parent_id);
        }

        return false;
    }

    /** Filtres par colonne (entonnoir des en-têtes). */
    protected function filtresColonnes(): array
    {
        return [
            'categorie' => self::filtreTexte('name'),
            'parent' => self::filtreRelation('parent', 'name'),
        ];
    }

    public function render(): View
    {
        return view('livewire.categories.category-manager', [
            'categories' => Category::enArbre(Category::query()
                ->withCount(['products', 'products as exoneres_count' => fn ($q) => $q->where('is_vat_exempt', true)])
                ->tap(fn ($q) => $this->appliquerFiltres($q))
                ->with('parent:id,name')
                ->get()),
            'parents' => Category::enArbre(Category::query()
                ->roots()
                ->when($this->enEdition, fn ($q) => $q->whereKeyNot($this->enEdition))
                ->get(['id', 'name', 'parent_id'])),
            'peutModifier'  => $this->peutModifier(),
            'peutSupprimer' => $this->peutModifier(),
            'gereTva'       => config('naneu.vat.enabled') && auth()->user()->hasAtLeast(UserRole::Gerant),
            'enCours'       => $this->enEdition
                ? Category::find($this->enEdition)
                : null,
        ]);
    }
}
