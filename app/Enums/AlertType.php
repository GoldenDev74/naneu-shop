<?php

namespace App\Enums;

enum AlertType: string
{
    case StockBas         = 'stock_bas';
    case Rupture          = 'rupture';
    case PeremptionProche = 'peremption_proche';
    case Perime           = 'perime';
    // Une vente a été refusée parce que le stock restant est périmé.
    case VenteBloquee     = 'vente_bloquee';

    public function label(): string
    {
        return __(match ($this) {
            self::StockBas         => 'Stock bas',
            self::Rupture          => 'Rupture de stock',
            self::PeremptionProche => 'Péremption proche',
            self::Perime           => 'Produit périmé',
            self::VenteBloquee     => 'Vente bloquée (produit périmé)',
        });
    }

    public function defaultSeverity(): AlertSeverity
    {
        return match ($this) {
            self::StockBas         => AlertSeverity::Avertissement,
            self::Rupture          => AlertSeverity::Critique,
            self::PeremptionProche => AlertSeverity::Avertissement,
            self::Perime           => AlertSeverity::Critique,
            self::VenteBloquee     => AlertSeverity::Critique,
        };
    }

    /** Ces alertes portent sur un lot précis, pas sur le produit entier. */
    public function isBatchScoped(): bool
    {
        return in_array($this, [self::PeremptionProche, self::Perime, self::VenteBloquee], true);
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $type) => [$type->value => $type->label()])
            ->all();
    }
}
