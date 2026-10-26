<?php

namespace App\Enums;

enum ExitType: string
{
    case Vente        = 'vente';
    case Perte        = 'perte';
    case Peremption   = 'peremption';
    case Casse        = 'casse';
    case Ajustement   = 'ajustement';
    case Retour       = 'retour_fournisseur';
    case Consommation = 'consommation';

    public function label(): string
    {
        return $this->toReason()->label();
    }

    public function toReason(): MovementReason
    {
        return MovementReason::from($this->value);
    }

    /** Seules les ventes génèrent du chiffre d'affaires. */
    public function generatesRevenue(): bool
    {
        return $this === self::Vente;
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Vente      => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300',
            self::Peremption,
            self::Perte,
            self::Casse      => 'bg-rose-100 text-rose-700 dark:bg-rose-900/40 dark:text-rose-300',
            default          => 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $type) => [$type->value => $type->label()])
            ->all();
    }
}
