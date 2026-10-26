<?php

namespace App\Support;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Str;

/**
 * Recherche insensible à la casse ET aux accents : « epicerie » doit trouver
 * « Épicerie ». La colonne est repliée côté base (sans extension unaccent,
 * absente de SQLite et rarement installée sous PostgreSQL), le terme côté PHP.
 *
 * Usage : ->whereLikeLoose('name', $terme) / ->orWhereLikeLoose(...)
 */
final class LooseSearch
{
    /** Lettres accentuées courantes en français, et leur forme repliée. */
    private const ACCENTS = 'àáâãäåçèéêëìíîïñòóôõöùúûüýÿÀÁÂÃÄÅÇÈÉÊËÌÍÎÏÑÒÓÔÕÖÙÚÛÜÝ';

    private const SANS    = 'aaaaaaceeeeiiiinooooouuuuyyaaaaaaceeeeiiiinooooouuuuy';

    public static function register(): void
    {
        Builder::macro('whereLikeLoose', function (string $column, string $term, string $boolean = 'and') {
            /** @var Builder $this */
            return $this->whereRaw(
                LooseSearch::foldedColumn($this->getGrammar()->wrap($column), $this->getConnection()->getDriverName()).' LIKE ?',
                ['%'.LooseSearch::fold($term).'%'],
                $boolean,
            );
        });

        Builder::macro('orWhereLikeLoose', function (string $column, string $term) {
            /** @var Builder $this */
            return $this->whereLikeLoose($column, $term, 'or');
        });
    }

    /** Terme replié : minuscules, sans accents. */
    public static function fold(string $term): string
    {
        return Str::lower(Str::ascii(trim($term)));
    }

    public static function foldedColumn(string $column, string $driver): string
    {
        if ($driver === 'pgsql') {
            return "translate(lower({$column}), '".self::ACCENTS."', '".self::SANS."')";
        }

        // SQLite : lower() ne connaît que l'ASCII, on remplace chaque lettre accentuée.
        $expression = "lower({$column})";
        $accents = mb_str_split(self::ACCENTS);
        $sans = str_split(self::SANS);

        foreach ($accents as $i => $lettre) {
            $expression = "replace({$expression}, '{$lettre}', '{$sans[$i]}')";
        }

        return $expression;
    }
}
