<?php

namespace App\Support;

use App\Models\Setting;

/**
 * Le franc CFA ne se divise pas en centimes. Tous les montants circulent
 * dans l'application en entiers ; cette classe ne sert qu'à l'affichage
 * et à la conversion depuis la saisie utilisateur.
 */
final class Money
{
    /**
     * Devise d'affichage choisie dans Paramètres (XOF par défaut).
     *
     * @return array{code: string, symbol: string, name: string, position: string}
     */
    public static function currency(?string $code = null): array
    {
        $code ??= (string) Setting::get('currency_code', config('naneu.currency.code', 'XOF'));
        $devises = config('naneu.currencies');

        if (! isset($devises[$code])) {
            $code = 'XOF';
        }

        return ['code' => $code] + $devises[$code];
    }

    /** Séparateur de milliers de la langue courante : « 12 500 » ou « 12,500 ». */
    public static function thousandsSeparator(): string
    {
        return app()->getLocale() === 'en' ? ',' : ' ';
    }

    /**
     * Ce qui entoure le nombre : « €12,500 » en anglais, « 12 500 € » en
     * français ; le franc CFA s'écrit toujours après (« 12 500 FCFA »).
     *
     * @return array{prefix: string, suffix: string}
     */
    public static function affixes(?string $code = null): array
    {
        $devise = self::currency($code);
        $avant = $devise['position'] === 'before'
            || ($devise['position'] === 'locale' && app()->getLocale() === 'en');

        return $avant
            ? ['prefix' => $devise['symbol'], 'suffix' => '']
            : ['prefix' => '', 'suffix' => ' '.$devise['symbol']];
    }

    /**
     * Nombre formaté selon la langue : « 1 234,5 » en français,
     * « 1,234.5 » en anglais. Sert aussi aux quantités.
     */
    public static function number(int|float|string|null $value, int $decimals = 0): string
    {
        $anglais = app()->getLocale() === 'en';

        return number_format((float) ($value ?? 0), $decimals, $anglais ? '.' : ',', self::thousandsSeparator());
    }

    public static function format(int|float|null $amount, bool $withSymbol = true): string
    {
        $formatted = number_format((float) ($amount ?? 0), 0, ',', self::thousandsSeparator());

        if (! $withSymbol) {
            return $formatted;
        }

        ['prefix' => $prefixe, 'suffix' => $suffixe] = self::affixes();

        return $prefixe.$formatted.$suffixe;
    }

    /**
     * Normalise une saisie clavier en entier : « 12 500 », « 12.500 » et
     * « 12,500 » valent 12 500 (séparateurs de milliers) ; « 12,5 » ou
     * « 12.50 » sont lus comme des décimales puis arrondis.
     */
    public static function parse(string|int|float|null $value): int
    {
        if ($value === null || $value === '') {
            return 0;
        }

        if (is_int($value) || is_float($value)) {
            return (int) round($value);
        }

        $clean = preg_replace('/[^0-9,.\-]/', '', $value) ?? '';

        if (preg_match('/^(.*)[.,](\d{1,2})$/', $clean, $m)) {
            $clean = str_replace([',', '.'], '', $m[1]).'.'.$m[2];
        } else {
            $clean = str_replace([',', '.'], '', $clean);
        }

        return (int) round((float) $clean);
    }

    /** Montant de TVA correspondant à un prix hors taxe. */
    public static function vatAmount(int $amountExcludingVat, float $rate): int
    {
        return (int) round($amountExcludingVat * $rate / 100);
    }

    /** Extrait la TVA contenue dans un montant TTC. */
    public static function vatFromInclusive(int $amountIncludingVat, float $rate): int
    {
        if ($rate <= 0) {
            return 0;
        }

        return (int) round($amountIncludingVat - ($amountIncludingVat / (1 + $rate / 100)));
    }

    /** Marge en pourcentage du prix de vente, arrondie au dixième. */
    public static function marginPercent(int $cost, int $salePrice): float
    {
        if ($salePrice <= 0) {
            return 0.0;
        }

        return round((($salePrice - $cost) / $salePrice) * 100, 1);
    }
}
