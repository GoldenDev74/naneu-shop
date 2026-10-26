<?php

namespace App\Enums;

/**
 * Droit d'un utilisateur sur un menu.
 *
 * Lecture : il consulte, les boutons de création, modification et
 * suppression lui sont cachés et refusés côté serveur.
 * Complet : tout ce que le menu permet de faire lui est ouvert — hors
 * actions réservées à la gérance (prix, annulations, validation
 * d'inventaire), qui ne dépendent pas des menus.
 */
enum AccessLevel: string
{
    case Lecture = 'lecture';
    case Complet = 'complet';

    public function label(): string
    {
        return __(match ($this) {
            self::Lecture => 'Lecture seule',
            self::Complet => 'Complet',
        });
    }
}
