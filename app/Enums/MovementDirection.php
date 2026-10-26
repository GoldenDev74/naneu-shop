<?php

namespace App\Enums;

enum MovementDirection: string
{
    case In  = 'in';
    case Out = 'out';

    public function label(): string
    {
        return $this === self::In ? __('Entrée') : __('Sortie');
    }

    public function sign(): int
    {
        return $this === self::In ? 1 : -1;
    }

    public function badgeClasses(): string
    {
        return $this === self::In
            ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300'
            : 'bg-orange-100 text-orange-700 dark:bg-orange-900/40 dark:text-orange-300';
    }
}
