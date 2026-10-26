<?php

namespace App\Enums;

enum MovementReason: string
{
    case Achat        = 'achat';
    case Vente        = 'vente';
    case Perte        = 'perte';
    case Peremption   = 'peremption';
    case Casse        = 'casse';
    case Ajustement   = 'ajustement';
    case Inventaire   = 'inventaire';
    case Retour       = 'retour_fournisseur';
    case Consommation = 'consommation';

    public function label(): string
    {
        return __(match ($this) {
            self::Achat        => 'Achat fournisseur',
            self::Vente        => 'Vente',
            self::Perte        => 'Perte',
            self::Peremption   => 'Péremption',
            self::Casse        => 'Casse',
            self::Ajustement   => 'Ajustement manuel',
            self::Inventaire   => "Écart d'inventaire",
            self::Retour       => 'Retour fournisseur',
            self::Consommation => 'Consommation interne',
        });
    }

    /**
     * Une sortie pour ces motifs est une perte sèche : elle alimente
     * le rapport de démarque.
     */
    public function isShrinkage(): bool
    {
        return in_array($this, [self::Perte, self::Peremption, self::Casse], true);
    }

    /**
     * Un lot périmé ne peut jamais être vendu ni consommé en interne. Les
     * autres sorties (perte, casse, retour, péremption, inventaire) peuvent
     * le prendre — et le prennent même en premier, pour écouler ce qui ne
     * peut plus servir.
     */
    public function usesExpiredStock(): bool
    {
        return ! in_array($this, [self::Vente, self::Consommation], true);
    }
}
