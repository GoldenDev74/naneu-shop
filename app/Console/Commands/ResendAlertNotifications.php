<?php

namespace App\Console\Commands;

use App\Models\StockAlert;
use App\Models\User;
use App\Notifications\StockAlertNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

/**
 * Rattrapage : notifie les alertes ouvertes qui n'ont jamais atteint la
 * cloche. Utile après un incident de file d'attente ou une reprise de
 * données, pour que l'écran « Alertes » et la cloche redisent la même
 * chose.
 */
class ResendAlertNotifications extends Command
{
    protected $signature = 'naneu:renotifier-alertes
                            {--all : Renotifier aussi les alertes déjà notifiées}';

    protected $description = 'Renvoie vers la cloche les alertes ouvertes non notifiées';

    public function handle(): int
    {
        $destinataires = User::query()->alertRecipients()->get();

        if ($destinataires->isEmpty()) {
            $this->components->error('Aucun destinataire actif (administrateur ou gérant).');

            return self::FAILURE;
        }

        $alertes = StockAlert::query()
            ->open()
            ->when(! $this->option('all'), fn ($q) => $q->whereNull('notified_at'))
            ->with(['product', 'batch'])
            ->bySeverity()
            ->get();

        if ($alertes->isEmpty()) {
            $this->components->info('Toutes les alertes ouvertes ont déjà été notifiées.');

            return self::SUCCESS;
        }

        $this->components->info(sprintf(
            'Envoi de %d alerte(s) à %d destinataire(s)…',
            $alertes->count(),
            $destinataires->count(),
        ));

        $barre = $this->output->createProgressBar($alertes->count());
        $barre->start();

        foreach ($alertes as $alerte) {
            Notification::send($destinataires, new StockAlertNotification($alerte));

            $alerte->forceFill(['notified_at' => now()])->save();

            $barre->advance();
        }

        $barre->finish();
        $this->newLine(2);

        $this->components->info($alertes->count().' alerte(s) renvoyée(s).');

        return self::SUCCESS;
    }
}
