<?php

namespace App\Enums;

enum AlertSeverity: string
{
    case Info          = 'info';
    case Avertissement = 'avertissement';
    case Critique      = 'critique';

    public function label(): string
    {
        return __(match ($this) {
            self::Info          => 'Information',
            self::Avertissement => 'Avertissement',
            self::Critique      => 'Critique',
        });
    }

    public function weight(): int
    {
        return match ($this) {
            self::Info          => 1,
            self::Avertissement => 2,
            self::Critique      => 3,
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Info          => 'bg-sky-100 text-sky-700 dark:bg-sky-900/40 dark:text-sky-300',
            self::Avertissement => 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300',
            self::Critique      => 'bg-rose-100 text-rose-700 dark:bg-rose-900/40 dark:text-rose-300',
        };
    }

    public function dotClasses(): string
    {
        return match ($this) {
            self::Info          => 'bg-sky-500',
            self::Avertissement => 'bg-amber-500',
            self::Critique      => 'bg-rose-500',
        };
    }
}
