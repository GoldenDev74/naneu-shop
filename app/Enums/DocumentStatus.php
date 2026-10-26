<?php

namespace App\Enums;

enum DocumentStatus: string
{
    case Brouillon = 'brouillon';
    case Valide    = 'valide';
    case Annule    = 'annule';

    public function label(): string
    {
        return __(match ($this) {
            self::Brouillon => 'Brouillon',
            self::Valide    => 'Validé',
            self::Annule    => 'Annulé',
        });
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Brouillon => 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300',
            self::Valide    => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300',
            self::Annule    => 'bg-rose-100 text-rose-700 dark:bg-rose-900/40 dark:text-rose-300',
        };
    }
}
