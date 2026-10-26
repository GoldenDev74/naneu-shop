<?php

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Product extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'sku', 'barcode', 'internal_code', 'name', 'slug', 'description',
        'category_id', 'unit_id', 'default_supplier_id',
        'sale_price', 'last_purchase_price', 'vat_rate', 'is_vat_exempt',
        'alert_quantity', 'reorder_quantity',
        'is_perishable', 'expiry_alert_days', 'shelf_life_days',
        'is_quick_item', 'image_path', 'display_order', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'sale_price'          => 'integer',
            'last_purchase_price' => 'integer',
            'vat_rate'            => 'decimal:2',
            'is_vat_exempt'       => 'boolean',
            'quantity_on_hand'    => 'decimal:3',
            'alert_quantity'      => 'decimal:3',
            'reorder_quantity'    => 'decimal:3',
            'is_perishable'       => 'boolean',
            'shelf_life_days'     => 'integer',
            'is_quick_item'       => 'boolean',
            'is_active'           => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Product $product) {
            if (blank($product->slug)) {
                $product->slug = static::uniqueSlug($product->name, $product->id);
            }

            // Un code-barres vide doit être NULL, jamais une chaîne vide :
            // la contrainte UNIQUE tolère plusieurs NULL, pas plusieurs ''.
            foreach (['barcode', 'internal_code'] as $column) {
                if ($product->{$column} === '') {
                    $product->{$column} = null;
                }
            }
        });
    }

    public static function uniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name) ?: 'produit';
        $slug = $base;
        $i = 2;

        while (static::withTrashed()
            ->where('slug', $slug)
            ->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))
            ->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }

    /** Génère la prochaine référence interne : NAN-0001, NAN-0002, … */
    public static function nextSku(): string
    {
        $last = static::withTrashed()
            ->where('sku', 'like', 'NAN-%')
            ->orderByDesc('id')
            ->value('sku');

        $number = $last ? ((int) Str::afterLast($last, '-')) + 1 : 1;

        return 'NAN-'.str_pad((string) $number, 4, '0', STR_PAD_LEFT);
    }

    // -----------------------------------------------------------------
    // Relations
    // -----------------------------------------------------------------

    /** @return BelongsTo<Category, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /** @return BelongsTo<Unit, $this> */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    /** @return BelongsTo<Supplier, $this> */
    public function defaultSupplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'default_supplier_id');
    }

    /** @return HasMany<StockBatch, $this> */
    public function batches(): HasMany
    {
        return $this->hasMany(StockBatch::class);
    }

    /**
     * Les lots encore consommables, triés dans l'ordre exact où la sortie
     * doit les vider. L'ordre dépend de la stratégie configurée.
     *
     * @return HasMany<StockBatch, $this>
     */
    public function availableBatches(): HasMany
    {
        return $this->batches()->available()->inConsumptionOrder();
    }

    /** @return HasMany<StockMovement, $this> */
    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    /** @return HasMany<StockAlert, $this> */
    public function alerts(): HasMany
    {
        return $this->hasMany(StockAlert::class);
    }

    /** @return HasMany<StockAlert, $this> */
    public function openAlerts(): HasMany
    {
        return $this->alerts()->whereNull('resolved_at');
    }

    // -----------------------------------------------------------------
    // État du stock
    // -----------------------------------------------------------------

    public function isOutOfStock(): bool
    {
        return (float) $this->quantity_on_hand <= 0;
    }

    public function isLowStock(): bool
    {
        $quantity = (float) $this->quantity_on_hand;

        return $quantity > 0 && $quantity <= (float) $this->alert_quantity;
    }

    /** rupture | bas | ok */
    public function stockStatus(): string
    {
        return match (true) {
            $this->isOutOfStock() => 'rupture',
            $this->isLowStock()   => 'bas',
            default               => 'ok',
        };
    }

    public function stockStatusClasses(): string
    {
        return match ($this->stockStatus()) {
            'rupture' => 'bg-rose-100 text-rose-700 dark:bg-rose-900/40 dark:text-rose-300',
            'bas'     => 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300',
            default   => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300',
        };
    }

    /** Nombre de jours avant alerte de péremption pour ce produit. */
    public function expiryAlertDays(): int
    {
        return $this->expiry_alert_days ?? (int) config('naneu.stock.expiry_alert_days', 30);
    }

    /**
     * Date limite d'un lot reçu le jour donné, quand le fournisseur n'en
     * imprime pas : réception + durée de conservation renseignée.
     */
    public function estimatedExpiryFor(\DateTimeInterface $receivedAt): ?Carbon
    {
        if (! $this->is_perishable || ! $this->shelf_life_days) {
            return null;
        }

        return Carbon::parse($receivedAt)->startOfDay()->addDays($this->shelf_life_days);
    }

    /** Date de péremption la plus proche parmi les lots encore en stock. */
    public function nearestExpiry(): ?Carbon
    {
        $date = $this->batches()
            ->available()
            ->whereNotNull('expiry_date')
            ->min('expiry_date');

        return $date ? Carbon::parse($date) : null;
    }

    /**
     * Valorisation du stock au coût réel : somme (quantité restante × prix
     * d'achat du lot). C'est la seule valeur juste en FIFO — un prix moyen
     * fausserait la marge dès que le fournisseur change ses tarifs.
     */
    public function stockValue(): int
    {
        return (int) round(
            $this->batches()
                ->available()
                ->get()
                ->sum(fn (StockBatch $batch) => (float) $batch->quantity_remaining * $batch->purchase_price)
        );
    }

    // -----------------------------------------------------------------
    // Prix
    // -----------------------------------------------------------------

    public function effectiveVatRate(): float
    {
        if ($this->is_vat_exempt || ! config('naneu.vat.enabled')) {
            return 0.0;
        }

        return (float) $this->vat_rate;
    }

    /**
     * Le prix de vente enregistré est TTC : c'est le prix affiché en rayon
     * et payé par le client. Le hors-taxe s'en déduit.
     */
    public function salePriceExcludingVat(): int
    {
        return $this->sale_price - Money::vatFromInclusive((int) $this->sale_price, $this->effectiveVatRate());
    }

    protected function formattedSalePrice(): Attribute
    {
        return Attribute::get(fn () => Money::format($this->sale_price));
    }

    // -----------------------------------------------------------------
    // Image (MinIO)
    // -----------------------------------------------------------------

    /**
     * Les objets MinIO sont privés : on sert une URL signée à durée de vie
     * courte plutôt qu'un lien public permanent.
     */
    public function imageUrl(): ?string
    {
        if (blank($this->image_path)) {
            return null;
        }

        $disk = config('naneu.storage.signing_disk');

        try {
            return Storage::disk($disk)->temporaryUrl(
                $this->image_path,
                now()->addMinutes((int) config('naneu.storage.temp_url_ttl', 15))
            );
        } catch (\Throwable) {
            // MinIO indisponible : la fiche produit reste utilisable sans image.
            return null;
        }
    }

    // -----------------------------------------------------------------
    // Scopes
    // -----------------------------------------------------------------

    /** @param  Builder<Product>  $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /** @param  Builder<Product>  $query */
    public function scopeLowStock(Builder $query): void
    {
        $query->whereColumn('quantity_on_hand', '<=', 'alert_quantity')
            ->where('quantity_on_hand', '>', 0);
    }

    /** @param  Builder<Product>  $query */
    public function scopeOutOfStock(Builder $query): void
    {
        $query->where('quantity_on_hand', '<=', 0);
    }

    /** @param  Builder<Product>  $query */
    public function scopeNeedsReorder(Builder $query): void
    {
        $query->whereColumn('quantity_on_hand', '<=', 'alert_quantity');
    }

    /**
     * Recherche unifiée : c'est le même champ qui reçoit la saisie clavier
     * et le scan de la douchette, donc le code-barres est cherché en égalité
     * stricte avant tout le reste.
     *
     * @param  Builder<Product>  $query
     */
    public function scopeSearch(Builder $query, ?string $term): void
    {
        if (blank($term)) {
            return;
        }

        $term = trim($term);

        $query->where(function (Builder $q) use ($term) {
            $q->where('barcode', $term)
                ->orWhere('internal_code', $term)
                ->orWhereLike('sku', "%{$term}%")
                ->orWhereLikeLoose('name', $term);
        });
    }

    /** @param  Builder<Product>  $query */
    public function scopeQuickItems(Builder $query): void
    {
        $query->where('is_quick_item', true)->orderBy('display_order');
    }
}
