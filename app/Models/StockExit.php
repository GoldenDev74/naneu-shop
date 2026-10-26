<?php

namespace App\Models;

use App\Enums\DocumentStatus;
use App\Enums\ExitType;
use App\Models\Concerns\HasSequentialReference;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockExit extends Model
{
    use HasSequentialReference;

    protected $fillable = [
        'reference', 'type', 'user_id', 'exit_date',
        'total_cost', 'total_sale', 'total_vat', 'total_margin', 'note', 'status',
        'payment_method', 'amount_tendered', 'change_given',
    ];

    protected function casts(): array
    {
        return [
            'type'         => ExitType::class,
            'exit_date'    => 'date',
            'total_cost'   => 'integer',
            'total_sale'   => 'integer',
            'total_vat'    => 'integer',
            'total_margin' => 'integer',
            'amount_tendered' => 'integer',
            'change_given'    => 'integer',
            'status'       => DocumentStatus::class,
        ];
    }

    /** SOR-2026-0001 — numéro attribué à l'enregistrement. */
    protected static function referencePrefix(): string
    {
        return 'SOR';
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<StockExitLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(StockExitLine::class);
    }

    /** @return HasMany<StockMovement, $this> */
    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class, 'source_id')
            ->where('source_type', static::class);
    }

    public function formattedTotalSale(): string
    {
        return Money::format($this->total_sale);
    }

    public function formattedTotalCost(): string
    {
        return Money::format($this->total_cost);
    }

    /** Marge en % du prix de vente hors taxe (la TVA n'appartient pas à la boutique). */
    public function marginPercent(): float
    {
        return Money::marginPercent($this->total_cost, $this->total_sale - $this->total_vat);
    }

    /** @param  Builder<StockExit>  $query */
    public function scopeValidated(Builder $query): void
    {
        $query->where('status', DocumentStatus::Valide->value);
    }

    /** @param  Builder<StockExit>  $query */
    public function scopeSales(Builder $query): void
    {
        $query->where('type', ExitType::Vente->value);
    }

    /** @param  Builder<StockExit>  $query */
    public function scopeShrinkage(Builder $query): void
    {
        $query->whereIn('type', [
            ExitType::Perte->value,
            ExitType::Peremption->value,
            ExitType::Casse->value,
        ]);
    }

    /** @param  Builder<StockExit>  $query */
    public function scopeBetween(Builder $query, string $from, string $to): void
    {
        // Borne haute en fin de journée : SQLite range les dates en texte
        // « 2026-10-19 00:00:00 », qui dépasserait « 2026-10-19 ».
        $query->whereBetween('exit_date', [$from, substr($to, 0, 10).' 23:59:59']);
    }
}
