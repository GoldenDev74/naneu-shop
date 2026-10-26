<?php

/**
 * Démonstration du moteur FIFO sur le catalogue réel.
 *
 *     php artisan tinker demo-fifo.php
 *
 * Fichier de démonstration — supprimable sans conséquence.
 */

use App\Data\EntryLineData;
use App\Data\ExitLineData;
use App\Enums\ExitType;
use App\Enums\UserRole;
use App\Models\Product;
use App\Models\StockAlert;
use App\Models\User;
use App\Services\AlertService;
use App\Services\StockService;
use App\Support\Money;
use Illuminate\Support\Carbon;

$stock = app(StockService::class);
$admin = User::where('role', UserRole::Admin->value)->first();
$odika = Product::where('sku', 'NAN-0001')->first();

echo PHP_EOL.'=== 3 arrivages d\'odika : 3 prix, 3 dates de péremption ==='.PHP_EOL;

$arrivages = [
    [20, 4800, '-40 days', '+120 days'],
    [30, 5200, '-20 days', '+25 days'],
    [25, 5600, '-2 days',  '+200 days'],
];

foreach ($arrivages as [$quantite, $prix, $recu, $peremption]) {
    $entree = $stock->receive(
        $admin,
        [new EntryLineData($odika->id, $quantite, $prix, Carbon::parse($peremption))],
        ['entry_date' => Carbon::parse($recu)->toDateString()],
    );

    printf(
        "  %s : %2d kg à %s   (reçu le %s, expire le %s)".PHP_EOL,
        $entree->reference,
        $quantite,
        Money::format($prix),
        Carbon::parse($recu)->format('d/m'),
        Carbon::parse($peremption)->format('d/m/Y'),
    );
}

$odika->refresh();

printf('  → stock %s kg, valorisé %s'.PHP_EOL.PHP_EOL,
    rtrim(rtrim((string) $odika->quantity_on_hand, '0'), '.'),
    Money::format($odika->stockValue()),
);

echo '=== Vente de 60 kg : le FIFO traverse les trois lots ==='.PHP_EOL;

$vente = $stock->issue($admin, ExitType::Vente, [
    new ExitLineData($odika->id, 60, $odika->sale_price),
]);

foreach ($vente->movements()->with('batch')->get() as $mouvement) {
    printf(
        '  %s : %5s kg à %s = %s'.PHP_EOL,
        $mouvement->batch->batch_number,
        rtrim(rtrim(number_format((float) $mouvement->quantity, 3, ',', ''), '0'), ','),
        Money::format($mouvement->unit_cost),
        Money::format($mouvement->total_cost),
    );
}

printf(PHP_EOL.'  Coût d\'achat réel (FIFO) : %s'.PHP_EOL, Money::format($vente->total_cost));
printf('  Chiffre d\'affaires       : %s'.PHP_EOL, Money::format($vente->total_sale));
printf('  Marge                    : %s (%s %% du prix de vente)'.PHP_EOL,
    Money::format($vente->total_margin), $vente->marginPercent());

$coutMoyen = (20 * 4800 + 30 * 5200 + 25 * 5600) / 75;
$approximation = (int) round(60 * $coutMoyen);

printf(
    PHP_EOL.'  À titre de comparaison, un coût moyen pondéré aurait annoncé %s,'.PHP_EOL
    .'  soit un écart de %s sur la marge de cette seule vente.'.PHP_EOL.PHP_EOL,
    Money::format($approximation),
    Money::format(abs($vente->total_cost - $approximation)),
);

echo '=== Alertes ouvertes automatiquement ==='.PHP_EOL;

// Un arrivage de poisson fumé du Gabon dont la DLC est déjà courte à
// réception : c'est le cas typique d'une filière d'import longue.
$poisson = Product::where('sku', 'NAN-0004')->first();

$stock->receive($admin, [
    new EntryLineData($poisson->id, 12, 6500, Carbon::now()->addDays(4)),
]);

// Puis une vente qui fait passer l'odika sous son seuil.
$stock->issue($admin, ExitType::Vente, [
    new ExitLineData($odika->id, 9, $odika->sale_price),
]);

app(AlertService::class)->scanExpiries();

foreach (StockAlert::query()->open()->bySeverity()->with('product')->limit(6)->get() as $alerte) {
    printf('  [%-13s] %s'.PHP_EOL, strtoupper($alerte->severity->value), $alerte->message);
}

echo PHP_EOL;
