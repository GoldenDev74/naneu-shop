<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Category extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name', 'slug', 'parent_id', 'color', 'description', 'is_active',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::saving(function (Category $category) {
            if (blank($category->slug)) {
                $category->slug = static::uniqueSlug($category->name, $category->id);
            }
        });
    }

    public static function uniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name) ?: 'categorie';
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

    /** @return BelongsTo<Category, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /** @return HasMany<Category, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /** @return HasMany<Product, $this> */
    /**
     * Cette catégorie et toutes ses sous-catégories : un inventaire du rayon
     * « Produits frais » doit compter aussi les fruits et légumes.
     *
     * @return array<int, int>
     */
    public function selfAndDescendantIds(): array
    {
        $ids = [$this->id];
        $niveau = [$this->id];

        while ($niveau !== []) {
            $niveau = self::query()->whereIn('parent_id', $niveau)->whereNotIn('id', $ids)->pluck('id')->all();
            $ids = array_merge($ids, $niveau);
        }

        return $ids;
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /** @param  Builder<Category>  $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /** @param  Builder<Category>  $query */
    public function scopeRoots(Builder $query): void
    {
        $query->whereNull('parent_id');
    }

    public function fullName(): string
    {
        return $this->parent ? "{$this->parent->name} › {$this->name}" : $this->name;
    }

    /**
     * Ordre d'affichage des listes : alphabétique à la française
     * (« Épicerie » avant « Riz », ce que l'ORDER BY de la base ne garantit
     * pas) et arborescent, chaque catégorie suivie de ses sous-catégories.
     * Renseigne `profondeur` (0 pour une racine) pour l'indentation.
     *
     * @param  Collection<int, Category>  $categories  avec parent_id chargé
     * @return Collection<int, Category>
     */
    public static function enArbre(Collection $categories): Collection
    {
        $collator = class_exists(\Collator::class) ? new \Collator('fr_FR') : null;
        $comparer = fn (Category $a, Category $b) => $collator
            ? $collator->compare($a->name, $b->name)
            : strcmp(Str::lower(Str::ascii($a->name)), Str::lower(Str::ascii($b->name)));

        $ids = $categories->pluck('id')->flip();
        // Parent absent de la liste (inactif, filtré) : la catégorie remonte en racine.
        $parBranche = $categories->groupBy(fn (Category $c) => $c->parent_id && $ids->has($c->parent_id) ? $c->parent_id : 0);

        $resultat = new Collection;
        $descendre = function (int $parent, int $profondeur) use (&$descendre, $parBranche, $comparer, $resultat) {
            foreach ($parBranche->get($parent, collect())->sort($comparer) as $categorie) {
                $categorie->profondeur = $profondeur;
                $resultat->push($categorie);
                $descendre($categorie->id, $profondeur + 1);
            }
        };
        $descendre(0, 0);

        return $resultat;
    }

    /** Libellé indenté pour un menu déroulant arborescent. */
    public function optionLabel(): string
    {
        return str_repeat('— ', (int) ($this->profondeur ?? 0)).$this->name;
    }
}
