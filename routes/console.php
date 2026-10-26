<?php

use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Tâches planifiées — Naneu Shop
|--------------------------------------------------------------------------
|
| Un seul processus à lancer en production :
|     php artisan schedule:work        (ou une entrée cron minute par minute)
|
| Toutes les heures sont locales (Africa/Libreville), fixées par
| APP_TIMEZONE. `withoutOverlapping` évite qu'un scan long ne se chevauche
| avec le suivant, et `onOneServer` protège d'un éventuel doublon si la
| boutique passe un jour à deux machines.
|
*/

$expiryTime = config('naneu.schedule.expiry_check', '07:00');
$lowStockTime = config('naneu.schedule.low_stock_check', '07:05');
$digestTime = config('naneu.schedule.daily_digest', '19:00');

// --- Alertes de péremption : au tout début de la journée de travail ------
Schedule::command('naneu:verifier-peremptions')
    ->dailyAt($expiryTime)
    ->withoutOverlapping(10)
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/planificateur.log'));

// --- Alertes de niveau de stock -----------------------------------------
Schedule::command('naneu:verifier-stock')
    ->dailyAt($lowStockTime)
    ->withoutOverlapping(10)
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/planificateur.log'));

// --- Second passage en milieu de journée --------------------------------
// Les ventes du matin font basculer des produits sous le seuil ; attendre
// le lendemain matin ferait perdre une demi-journée de réapprovisionnement.
Schedule::command('naneu:verifier-stock')
    ->dailyAt('13:00')
    ->withoutOverlapping(10)
    ->runInBackground();

// --- Résumé du jour ------------------------------------------------------
Schedule::command('naneu:resume-quotidien')
    ->dailyAt($digestTime)
    ->withoutOverlapping()
    ->runInBackground();

// --- Contrôle d'intégrité hebdomadaire -----------------------------------
// Vérifie que le cache de quantité colle aux lots. Sans --fix : on veut
// être prévenu d'une dérive, pas la masquer silencieusement.
Schedule::command('naneu:recalculer-stock')
    ->weeklyOn(1, '06:00')
    ->appendOutputTo(storage_path('logs/planificateur.log'));

// --- Entretien -----------------------------------------------------------
Schedule::command('queue:prune-batches --hours=48')->daily();
Schedule::command('model:prune')->daily();
Schedule::command('auth:clear-resets')->daily();
