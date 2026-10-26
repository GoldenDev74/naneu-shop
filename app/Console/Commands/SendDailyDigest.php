<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Notifications\DailyDigestNotification;
use App\Services\ReportService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

class SendDailyDigest extends Command
{
    protected $signature = 'naneu:resume-quotidien {--date= : Jour à résumer (AAAA-MM-JJ)}';

    protected $description = 'Envoie le résumé du jour aux administrateurs et gérants';

    public function handle(ReportService $reports): int
    {
        $date = $this->option('date') ?: now()->toDateString();

        $digest = $reports->dailyDigest($date);

        $recipients = User::query()->alertRecipients()->get();

        if ($recipients->isEmpty()) {
            $this->components->warn('Aucun destinataire actif.');

            return self::SUCCESS;
        }

        Notification::send($recipients, new DailyDigestNotification($date, $digest));

        $this->components->info(sprintf(
            'Résumé du %s envoyé à %d destinataire(s).',
            \Illuminate\Support\Carbon::parse($date)->format('d/m/Y'),
            $recipients->count(),
        ));

        return self::SUCCESS;
    }
}
