<?php

namespace App\Models\Concerns;

use Illuminate\Support\Facades\DB;

/**
 * Numérotation des pièces (ENT-2026-0001, SOR-…, INV-…, CHG-…).
 *
 * Le numéro est attribué au moment de l'enregistrement, jamais à
 * l'ouverture d'un formulaire : deux saisies simultanées obtiendraient
 * sinon la même référence. On repart du plus grand numéro existant (et non
 * d'un comptage, qui recycle un numéro après suppression) et, sous
 * PostgreSQL, un verrou consultatif de transaction sérialise les attributions.
 */
trait HasSequentialReference
{
    abstract protected static function referencePrefix(): string;

    public static function nextReference(): string
    {
        $prefixe = static::referencePrefix().'-'.now()->year.'-';

        $connexion = DB::connection((new static)->getConnectionName());

        if ($connexion->getDriverName() === 'pgsql' && $connexion->transactionLevel() > 0) {
            $connexion->select('select pg_advisory_xact_lock(?)', [crc32($prefixe)]);
        }

        $requete = static::query();

        if (method_exists(static::class, 'bootSoftDeletes')) {
            $requete->withTrashed();
        }

        $derniere = $requete->where('reference', 'like', $prefixe.'%')
            ->pluck('reference')
            ->map(fn (string $reference) => (int) substr($reference, strlen($prefixe)))
            ->max() ?? 0;

        return sprintf('%s%04d', $prefixe, $derniere + 1);
    }
}
