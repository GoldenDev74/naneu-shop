<?php

namespace App\Models;

use App\Models\Concerns\HasSequentialReference;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Charge complémentaire : transport, vignettes, électricité / eau…
 * Déduite de la marge brute pour obtenir le bénéfice net.
 */
class Expense extends Model
{
    use SoftDeletes, HasSequentialReference;

    protected $fillable = [
        'reference', 'expense_category_id', 'expense_date', 'label',
        'amount', 'stock_entry_id', 'user_id', 'note',
    ];

    protected function casts(): array
    {
        return [
            'expense_date' => 'date',
            'amount'       => 'integer',
        ];
    }

    /** CHG-2026-0001 — numéro attribué à l'enregistrement. */
    protected static function referencePrefix(): string
    {
        return 'CHG';
    }

    /** @return BelongsTo<ExpenseCategory, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id');
    }

    /** @return BelongsTo<StockEntry, $this> */
    public function stockEntry(): BelongsTo
    {
        return $this->belongsTo(StockEntry::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @param  Builder<Expense>  $query */
    public function scopeBetween(Builder $query, string $from, string $to): void
    {
        // Borne haute en fin de journée : SQLite range les dates en texte
        // « 2026-10-19 00:00:00 », qui dépasserait « 2026-10-19 ».
        $query->whereBetween('expense_date', [$from, substr($to, 0, 10).' 23:59:59']);
    }
}
