<?php

namespace App\Livewire\Products;

use App\Enums\UserRole;
use App\Livewire\Concerns\InteractsWithToasts;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\Unit;
use App\Support\Money;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

#[Layout('layouts.app')]
class ProductForm extends Component
{
    use InteractsWithToasts, WithFileUploads;

    public ?Product $product = null;

    // --- Identification ---
    public string $sku = '';
    public string $barcode = '';
    public string $internal_code = '';
    public string $name = '';
    public string $description = '';

    // --- Classification ---
    public ?int $category_id = null;
    public ?int $unit_id = null;
    public ?int $default_supplier_id = null;

    // --- Prix (saisis en texte pour accepter « 12 500 » comme « 12500 ») ---
    public string $sale_price = '0';
    public string $vat_rate = '18';
    public bool $is_vat_exempt = false;

    // --- Stock ---
    public string $alert_quantity = '5';
    public string $reorder_quantity = '0';

    // --- Péremption ---
    // Décoché par défaut (hygiène, entretien…) ; proposé selon la catégorie choisie.
    public bool $is_perishable = false;
    public ?int $expiry_alert_days = null;
    public ?int $shelf_life_days = null;

    // --- Présentation ---
    public bool $is_quick_item = false;
    public bool $is_active = true;
    public $image = null;

    public function mount(?Product $product = null): void
    {
        if ($product?->exists) {
            $this->authorize('update', $product);

            $this->product = $product;

            $this->fill([
                'sku'                 => $product->sku,
                'barcode'             => (string) $product->barcode,
                'internal_code'       => (string) $product->internal_code,
                'name'                => $product->name,
                'description'         => (string) $product->description,
                'category_id'         => $product->category_id,
                'unit_id'             => $product->unit_id,
                'default_supplier_id' => $product->default_supplier_id,
                'sale_price'          => (string) $product->sale_price,
                'vat_rate'            => (string) $product->vat_rate,
                'is_vat_exempt'       => $product->is_vat_exempt,
                'alert_quantity'      => rtrim(rtrim((string) $product->alert_quantity, '0'), '.') ?: '0',
                'reorder_quantity'    => rtrim(rtrim((string) $product->reorder_quantity, '0'), '.') ?: '0',
                'is_perishable'       => $product->is_perishable,
                'expiry_alert_days'   => $product->expiry_alert_days,
                'shelf_life_days'     => $product->shelf_life_days,
                'is_quick_item'       => $product->is_quick_item,
                'is_active'           => $product->is_active,
            ]);

            return;
        }

        $this->authorize('create', Product::class);

        $this->sku = Product::nextSku();
        $this->vat_rate = (string) config('naneu.vat.default_rate', 18);
        $this->alert_quantity = (string) config('naneu.stock.low_stock_threshold', 5);
        $this->unit_id = Unit::where('name', 'Pièce')->value('id');
    }

    /**
     * Nouveau produit : la case « périssable » suit ce qui se fait dans la
     * catégorie (la majorité de ses produits), sans écraser un choix sur une
     * fiche existante.
     */
    public function updatedCategoryId(): void
    {
        if ($this->product || ! $this->category_id) {
            return;
        }

        $produits = Product::where('category_id', $this->category_id);
        $total = (clone $produits)->count();

        if ($total > 0) {
            $this->is_perishable = (clone $produits)->where('is_perishable', true)->count() * 2 > $total;
        }
    }

    /** @return array<string, mixed> */
    protected function rules(): array
    {
        $id = $this->product?->id;

        return [
            'sku' => [
                'required', 'string', 'max:50',
                Rule::unique('products', 'sku')->ignore($id)->withoutTrashed(),
            ],
            // Un EAN-13 fait 13 chiffres, un EAN-8 en fait 8 ; on accepte
            // aussi les UPC-A (12) que l'on croise sur les produits importés.
            'barcode' => [
                'nullable', 'string', 'regex:/^[0-9]{8,14}$/',
                Rule::unique('products', 'barcode')->ignore($id)->withoutTrashed(),
            ],
            'internal_code' => [
                'nullable', 'string', 'max:50',
                Rule::unique('products', 'internal_code')->ignore($id)->withoutTrashed(),
            ],
            'name'                => ['required', 'string', 'max:180'],
            'description'         => ['nullable', 'string', 'max:2000'],
            'category_id'         => ['nullable', 'exists:categories,id'],
            'unit_id'             => ['required', 'exists:units,id'],
            'default_supplier_id' => ['nullable', 'exists:suppliers,id'],
            'sale_price'          => ['required', 'string'],
            'vat_rate'            => ['required', 'numeric', 'min:0', 'max:100'],
            'is_vat_exempt'       => ['boolean'],
            'alert_quantity'      => ['required', 'numeric', 'min:0'],
            'reorder_quantity'    => ['nullable', 'numeric', 'min:0'],
            'is_perishable'       => ['boolean'],
            'expiry_alert_days'   => ['nullable', 'integer', 'min:1', 'max:365'],
            'shelf_life_days'     => ['nullable', 'integer', 'min:1', 'max:730'],
            'is_quick_item'       => ['boolean'],
            'is_active'           => ['boolean'],
            'image'               => [
                'nullable', 'image',
                'mimes:'.implode(',', config('naneu.storage.image_mimes')),
                'max:'.config('naneu.storage.max_image_kb'),
            ],
        ];
    }

    /** @return array<string, string> */
    protected function messages(): array
    {
        return [
            'barcode.regex'   => 'Un code-barres doit contenir entre 8 et 14 chiffres (EAN-8, UPC-A ou EAN-13).',
            'barcode.unique'  => 'Ce code-barres est déjà attribué à un autre produit.',
            'sku.unique'      => 'Cette référence interne est déjà utilisée.',
            'name.required'   => 'Le nom du produit est obligatoire.',
            'unit_id.required' => 'Choisissez l\'unité de vente (pièce, kilogramme, litre…).',
            'image.max'       => 'L\'image ne doit pas dépasser :max Ko.',
        ];
    }

    /** @return array<string, string> */
    protected function validationAttributes(): array
    {
        return [
            'sku'                 => 'référence',
            'barcode'             => 'code-barres',
            'internal_code'       => 'code interne',
            'name'                => 'nom',
            'category_id'         => 'catégorie',
            'unit_id'             => 'unité',
            'default_supplier_id' => 'fournisseur',
            'sale_price'          => 'prix de vente',
            'vat_rate'            => 'taux de TVA',
            'alert_quantity'      => 'seuil d\'alerte',
        ];
    }

    /** Génère un code interne pour un produit vendu sans code-barres fabricant. */
    public function genererCodeInterne(): void
    {
        do {
            $code = 'NS'.str_pad((string) random_int(1, 9999999), 7, '0', STR_PAD_LEFT);
        } while (Product::withTrashed()->where('internal_code', $code)->exists());

        $this->internal_code = $code;

        $this->toastInfo('Code interne généré. Il pourra être imprimé sur étiquette si vous vous équipez d\'une imprimante thermique.');
    }

    public function enregistrer()
    {
        $donnees = $this->validate();

        $peutFixerPrix = auth()->user()->hasAtLeast(UserRole::Gerant);

        $attributs = [
            'sku'                 => $donnees['sku'],
            'barcode'             => $donnees['barcode'] ?: null,
            'internal_code'       => $donnees['internal_code'] ?: null,
            'name'                => $donnees['name'],
            'description'         => $donnees['description'] ?: null,
            'category_id'         => $donnees['category_id'],
            'unit_id'             => $donnees['unit_id'],
            'default_supplier_id' => $donnees['default_supplier_id'],
            'alert_quantity'      => (float) str_replace(',', '.', $donnees['alert_quantity']),
            'reorder_quantity'    => (float) str_replace(',', '.', $donnees['reorder_quantity'] ?: '0'),
            'is_perishable'       => $donnees['is_perishable'],
            'expiry_alert_days'   => $donnees['expiry_alert_days'],
            'shelf_life_days'     => $donnees['is_perishable'] ? $donnees['shelf_life_days'] : null,
            'is_quick_item'       => $donnees['is_quick_item'],
            'is_active'           => $donnees['is_active'],
        ];

        // Le prix n'est écrit que si l'utilisateur a le droit d'y toucher :
        // sans ce filtre, un magasinier qui édite une fiche remettrait à
        // zéro un prix qu'il ne voit même pas à l'écran.
        if ($peutFixerPrix) {
            $attributs['sale_price']    = Money::parse($donnees['sale_price']);
            $attributs['vat_rate']      = (float) $donnees['vat_rate'];
            $attributs['is_vat_exempt'] = $donnees['is_vat_exempt'];
        }

        $nouveau = $this->product === null;

        $produit = $this->product ?? new Product;
        $produit->fill($attributs)->save();

        if ($produit->wasChanged('shelf_life_days') || $nouveau) {
            app(\App\Services\StockService::class)->applyShelfLife($produit);
        }

        $imageEnvoyee = ! $this->image || $this->televerserImage($produit);

        AuditLog::record(
            $nouveau ? 'produit.cree' : 'produit.modifie',
            auth()->user(),
            $produit,
            ['sku' => $produit->sku, 'nom' => $produit->name],
        );

        // Image refusée : la fiche est enregistrée, mais on rouvre le produit
        // avec le message affiché en haut de page — un toast suivi d'une
        // redirection disparaissait avant d'avoir été lu.
        if (! $imageEnvoyee) {
            session()->flash('erreur', __('Produit « :nom » enregistré, mais la photo n\'a pas pu être envoyée. Vérifiez le stockage des fichiers puis choisissez à nouveau la photo.', ['nom' => $produit->name]));

            return $this->redirectRoute('produits.edit', ['product' => $produit], navigate: true);
        }

        session()->flash('succes', $nouveau
            ? __('Produit « :nom » créé.', ['nom' => $produit->name])
            : __('Produit « :nom » mis à jour.', ['nom' => $produit->name]));

        return $this->redirectRoute('produits.index', navigate: true);
    }

    /**
     * L'image part sur MinIO. Un échec de téléversement ne doit pas faire
     * perdre la fiche produit : on la garde, on signale l'image manquante.
     */
    private function televerserImage(Product $produit): bool
    {
        try {
            /** @var TemporaryUploadedFile $fichier */
            $fichier = $this->image;

            $chemin = $fichier->storePublicly(
                config('naneu.storage.products_path'),
                ['disk' => config('naneu.storage.disk')],
            );

            if (! $chemin) {
                throw new \RuntimeException('Le disque a refusé le fichier.');
            }

            $ancien = $produit->image_path;

            $produit->forceFill(['image_path' => $chemin])->save();

            if ($ancien && $ancien !== $chemin) {
                \Illuminate\Support\Facades\Storage::disk(config('naneu.storage.disk'))->delete($ancien);
            }

            return true;
        } catch (\Throwable $e) {
            report($e);

            return false;
        }
    }

    /**
     * TVA contenue dans le prix TTC saisi, recalculée à chaque frappe.
     *
     * @return array{tva: int, ht: int}
     */
    private function decomposition(): array
    {
        $ttc = Money::parse($this->sale_price);
        $taux = (! config('naneu.vat.enabled') || $this->is_vat_exempt) ? 0.0 : (float) str_replace(',', '.', $this->vat_rate);
        $tva = Money::vatFromInclusive($ttc, max(0.0, $taux));

        return ['tva' => $tva, 'ht' => $ttc - $tva];
    }

    public function render(): View
    {
        return view('livewire.products.product-form', [
            // `parent` est chargé d'office : la liste déroulante affiche
            // « Parent › Enfant », ce qui déclencherait sinon une requête
            // par catégorie.
            'categories'    => Category::enArbre(Category::query()->active()->with('parent:id,name')->get()),
            'unites'        => Unit::query()->orderBy('name')->get(),
            'fournisseurs'  => Supplier::query()->active()->orderBy('name')->get(),
            'peutFixerPrix' => auth()->user()->hasAtLeast(UserRole::Gerant),
            'tvaActive'     => (bool) config('naneu.vat.enabled'),
            'decomposition' => $this->decomposition(),
            'titre'         => $this->product ? 'Modifier le produit' : 'Nouveau produit',
        ]);
    }
}
