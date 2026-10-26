<?php

namespace App\Livewire\Concerns;

use App\Support\Money;
use Closure;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Support\Carbon;

/**
 * Filtres par colonne des tableaux (icône entonnoir dans l'en-tête,
 * composant x-table.th). Le filtre s'applique en base, donc à toutes les
 * lignes et pas seulement à la page affichée.
 *
 * Le composant déclare ses colonnes filtrables dans filtresColonnes() :
 * clé => fonction (requête, valeur saisie). Les assistants ci-dessous
 * couvrent les cas courants : texte, égalité, date, nombre, relation.
 */
trait WithColumnFilters
{
    /** @var array<string, string> */
    public array $filtres = [];

    /** @return array<string, Closure(Builder, string): mixed> */
    abstract protected function filtresColonnes(): array;

    public function updatedFiltres(): void
    {
        if (method_exists($this, 'resetPage')) {
            $this->resetPage();
        }
    }

    public function effacerFiltres(): void
    {
        $this->filtres = [];
        $this->updatedFiltres();
    }

    public function filtresActifs(): int
    {
        return collect($this->filtres)->filter(fn ($v) => trim((string) $v) !== '')->count();
    }

    /**
     * @template T of Builder
     * @param  T  $query
     * @return T
     */
    protected function appliquerFiltres(Builder $query): Builder
    {
        foreach ($this->filtresColonnes() as $cle => $filtre) {
            $valeur = trim((string) ($this->filtres[$cle] ?? ''));

            if ($valeur !== '') {
                $filtre($query, $valeur);
            }
        }

        return $query;
    }

    // -----------------------------------------------------------------
    // Assistants
    // -----------------------------------------------------------------

    /** Contient le texte saisi, sans tenir compte des accents ni de la casse. */
    protected static function filtreTexte(string ...$colonnes): Closure
    {
        return fn (Builder $q, string $v) => $q->where(function ($sous) use ($colonnes, $v) {
            foreach ($colonnes as $i => $colonne) {
                $i === 0 ? $sous->whereLikeLoose($colonne, $v) : $sous->orWhereLikeLoose($colonne, $v);
            }
        });
    }

    /** Valeur exacte (liste déroulante). */
    protected static function filtreEgal(string $colonne): Closure
    {
        return fn (Builder $q, string $v) => $q->where($colonne, $v);
    }

    /** Jour exact (champ date). */
    protected static function filtreDate(string $colonne): Closure
    {
        return function (Builder $q, string $v) use ($colonne) {
            try {
                $q->whereDate($colonne, Carbon::parse($v)->toDateString());
            } catch (\Throwable) {
                // Date incomplète en cours de saisie : on n'applique rien.
            }
        };
    }

    /**
     * Nombre : « 10 », « >10 », « <=5 », « 10-20 ». $montant : valeur saisie
     * en monnaie (« 12 500 »), comparée aux montants entiers stockés.
     */
    protected static function filtreNombre(string $colonne, bool $montant = false): Closure
    {
        $lire = fn (string $s) => $montant
            ? Money::parse($s)
            : (float) str_replace([' ', ','], ['', '.'], $s);

        return function (Builder $q, string $v) use ($colonne, $lire) {
            $v = str_replace("\u{202F}", ' ', $v);

            if (preg_match('/^([\d\s.,]+)\s*(?:-|\.\.|à)\s*([\d\s.,]+)$/u', $v, $m)) {
                $q->whereBetween($colonne, [$lire($m[1]), $lire($m[2])]);
            } elseif (preg_match('/^(<=|>=|<|>|=)?\s*(-?[\d\s.,]+)$/u', $v, $m)) {
                $q->where($colonne, $m[1] ?: '=', $lire($m[2]));
            }
        };
    }

    /** Texte cherché dans une relation (produit, fournisseur, auteur…). */
    protected static function filtreRelation(string $relation, string ...$colonnes): Closure
    {
        $texte = self::filtreTexte(...$colonnes);

        return fn (Builder $q, string $v) => $q->whereHas($relation, fn ($r) => $texte($r, $v));
    }
}
