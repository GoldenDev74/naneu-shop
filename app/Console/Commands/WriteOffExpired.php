<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\StockBatch;
use App\Models\User;
use App\Services\StockService;
use App\Support\Money;
use Illuminate\Console\Command;

/**
 * Sort du stock les lots périmés. Volontairement NON planifiée par défaut :
 * retirer de la marchandise est une décision de gestion, pas un automatisme.
 * Le gérant la déclenche depuis l'écran « Péremptions », ou on l'active
 * dans le planificateur si la boutique le souhaite.
 */
class WriteOffExpired extends Command
{
    protected $signature = 'naneu:sortir-perimes
                            {--force : Ne pas demander de confirmation}
                            {--user= : Identifiant du compte à qui imputer la sortie}';

    protected $description = 'Retire du stock tous les lots périmés et enregistre la perte';

    public function handle(StockService $stock): int
    {
        $batches = StockBatch::query()->available()->expired()->with('product')->get();

        if ($batches->isEmpty()) {
            $this->components->info('Aucun lot périmé en stock.');

            return self::SUCCESS;
        }

        $value = $batches->sum(fn (StockBatch $b) => $b->remainingValue());

        $this->components->warn(sprintf(
            '%d lot(s) périmé(s), valeur d\'achat %s.',
            $batches->count(),
            Money::format($value),
        ));

        $this->table(
            ['Produit', 'Lot', 'Péremption', 'Restant', 'Valeur'],
            $batches->map(fn (StockBatch $b) => [
                $b->product?->name ?? '—',
                $b->batch_number,
                $b->expiry_date?->format('d/m/Y'),
                rtrim(rtrim(number_format((float) $b->quantity_remaining, 3, ',', ' '), '0'), ','),
                Money::format($b->remainingValue()),
            ])->all(),
        );

        if (! $this->option('force') && ! $this->confirm('Confirmer la sortie de ces lots ?', false)) {
            $this->components->info('Annulé.');

            return self::SUCCESS;
        }

        $user = $this->resolveUser();

        if (! $user) {
            $this->components->error('Aucun administrateur actif trouvé pour imputer la sortie.');

            return self::FAILURE;
        }

        $result = $stock->writeOffExpired($user);

        $this->components->info(sprintf(
            '%d lot(s) sorti(s) — perte enregistrée : %s.',
            $result['lots'],
            Money::format($result['valeur']),
        ));

        return self::SUCCESS;
    }

    private function resolveUser(): ?User
    {
        if ($id = $this->option('user')) {
            return User::find($id);
        }

        return User::query()->active()->role(UserRole::Admin)->orderBy('id')->first();
    }
}
