<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Identité de la boutique
    |--------------------------------------------------------------------------
    */

    'shop' => [
        'name'    => env('NANEU_SHOP_NAME', 'Naneu Shop'),
        'tagline' => env('NANEU_SHOP_TAGLINE', 'Alimentation générale — spécialiste des produits gabonais'),
        'slogan'  => env('NANEU_SHOP_SLOGAN', 'Se faire plaisir est un plaisir'),
        'phone'   => env('NANEU_SHOP_PHONE', ''),
        'address' => env('NANEU_SHOP_ADDRESS', 'Lomé, Togo'),
        'nif'     => env('NANEU_SHOP_NIF', ''),
    ],

    /*
    |--------------------------------------------------------------------------
    | Devise
    |--------------------------------------------------------------------------
    | Le franc CFA d'Afrique de l'Ouest (XOF, zone BCEAO — celle du Togo)
    | n'a pas de subdivision utilisée dans les échanges. Tous les montants
    | sont donc stockés en entiers, ce qui écarte toute erreur d'arrondi
    | flottant sur les calculs de marge.
    |
    | À noter pour les achats au Gabon : le XAF (zone BEAC) et le XOF ont
    | la même parité fixe avec l'euro, donc 1 XAF = 1 XOF. Les prix d'achat
    | relevés chez un fournisseur gabonais se reportent tels quels.
    */

    'currency' => [
        'code'      => env('NANEU_CURRENCY', 'XOF'),
        'symbol'    => 'FCFA',
        'decimals'  => 0,
        'separator' => ' ',
    ],

    /*
    | Devises proposées dans Paramètres. La devise ne change que l'affichage :
    | les montants restent des entiers, sans conversion ni centimes.
    | position : avant (« €12 ») ou après (« 12 FCFA ») le nombre.
    */
    'currencies' => [
        'XOF' => ['symbol' => 'FCFA', 'name' => 'Franc CFA BCEAO (XOF)', 'position' => 'after'],
        'XAF' => ['symbol' => 'FCFA', 'name' => 'Franc CFA BEAC (XAF)', 'position' => 'after'],
        'EUR' => ['symbol' => '€', 'name' => 'Euro (EUR)', 'position' => 'locale'],
        'USD' => ['symbol' => '$', 'name' => 'Dollar US (USD)', 'position' => 'locale'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Langues d'interface
    |--------------------------------------------------------------------------
    */

    'locales' => [
        'fr' => 'Français',
        'en' => 'English',
    ],

    /*
    |--------------------------------------------------------------------------
    | Fiscalité
    |--------------------------------------------------------------------------
    */

    /*
    | Caisse : un long ticket est découpé en pages de N articles ; seule la
    | dernière porte le total, les autres un sous-total « à reporter ».
    */
    'pos' => [
        'ticket_lines_per_page' => (int) env('NANEU_TICKET_LINES_PER_PAGE', 8),
    ],

    // Prix de vente saisi à la main (bon de sortie) : au-delà de ce
    // pourcentage de remise sur le prix catalogue, seule la gérance valide.
    'sales' => [
        'max_discount_percent' => (float) env('NANEU_MAX_DISCOUNT_PERCENT', 10),
    ],

    // Connexion : verrouillage du compte après des échecs répétés, et
    // déconnexion d'une session restée sans activité (0 = jamais).
    'security' => [
        'max_failed_logins' => (int) env('NANEU_MAX_FAILED_LOGINS', 5),
        'lockout_minutes'   => (int) env('NANEU_LOCKOUT_MINUTES', 15),
        'idle_minutes'      => (int) env('NANEU_IDLE_MINUTES', 30),
    ],

    'vat' => [
        'default_rate' => (float) env('NANEU_VAT_RATE', 18),
        'enabled'      => (bool) env('NANEU_VAT_ENABLED', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Stratégie de consommation du stock
    |--------------------------------------------------------------------------
    | fifo : premier entré, premier sorti (ordre de réception strict).
    | fefo : premier périmé, premier sorti (recommandé en alimentaire —
    |        réduit les pertes par péremption).
    */

    'stock' => [
        'strategy'             => env('NANEU_CONSUMPTION_STRATEGY', 'fifo'),
        'allow_negative'       => false,
        'expiry_alert_days'    => (int) env('NANEU_EXPIRY_ALERT_DAYS', 30),
        'expiry_critical_days' => (int) env('NANEU_EXPIRY_CRITICAL_DAYS', 7),
        'low_stock_threshold'  => (int) env('NANEU_LOW_STOCK_THRESHOLD', 5),
    ],

    /*
    |--------------------------------------------------------------------------
    | Planificateur (heures locales Africa/Lome)
    |--------------------------------------------------------------------------
    */

    'schedule' => [
        'expiry_check'    => env('NANEU_EXPIRY_CHECK_TIME', '07:00'),
        'low_stock_check' => env('NANEU_LOW_STOCK_CHECK_TIME', '07:05'),
        'daily_digest'    => env('NANEU_DIGEST_TIME', '19:00'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Stockage des fichiers
    |--------------------------------------------------------------------------
    */

    'storage' => [
        'disk'            => env('NANEU_STORAGE_DISK', 'minio'),
        // Disque utilisé pour signer les liens ouverts par le navigateur.
        'signing_disk'    => env('NANEU_STORAGE_SIGNING_DISK', 'minio_public'),
        'products_path'   => 'produits',
        'exports_path'    => 'exports',
        'max_image_kb'    => 2048,
        'image_mimes'     => ['jpg', 'jpeg', 'png', 'webp'],
        'temp_url_ttl'    => 15, // minutes
    ],

];
