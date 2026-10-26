<?php

namespace App\Support;

use App\Models\Setting;

/**
 * Relie l'écran Paramètres au reste de l'application.
 *
 * Les réglages enregistrés en base remplacent les valeurs de config/naneu.php
 * (elles-mêmes issues du .env). Tout le code qui lit config('naneu…') suit
 * donc ce que la gérante a choisi, sans connaître la table settings.
 */
final class ShopSettings
{
    /** Clé de réglage → chemin de configuration. */
    private const CORRESPONDANCES = [
        'shop_name'            => 'naneu.shop.name',
        'shop_phone'           => 'naneu.shop.phone',
        'shop_address'         => 'naneu.shop.address',
        'shop_nif'             => 'naneu.shop.nif',
        'stock_strategy'       => 'naneu.stock.strategy',
        'expiry_alert_days'    => 'naneu.stock.expiry_alert_days',
        'expiry_critical_days' => 'naneu.stock.expiry_critical_days',
        'low_stock_threshold'  => 'naneu.stock.low_stock_threshold',
        'vat_enabled'          => 'naneu.vat.enabled',
        'vat_default_rate'     => 'naneu.vat.default_rate',
        'currency_code'        => 'naneu.currency.code',
        'max_discount_percent' => 'naneu.sales.max_discount_percent',
        'idle_minutes'         => 'naneu.security.idle_minutes',
        'max_failed_logins'    => 'naneu.security.max_failed_logins',
        'lockout_minutes'      => 'naneu.security.lockout_minutes',
    ];

    public static function apply(): void
    {
        try {
            $reglages = Setting::cached();
        } catch (\Throwable) {
            // Installation neuve ou migration en cours : la table n'existe
            // pas encore, les valeurs du .env restent en vigueur.
            return;
        }

        $valeurs = [];

        foreach (self::CORRESPONDANCES as $cle => $chemin) {
            if (array_key_exists($cle, $reglages) && $reglages[$cle] !== null && $reglages[$cle] !== '') {
                $valeurs[$chemin] = $reglages[$cle];
            }
        }

        config($valeurs);
    }
}
