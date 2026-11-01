<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FinancialCampaign extends Model
{
    protected $fillable = [
        'code',
        'name',
        'description',
        'starts_on',
        'ends_on',
        'status',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
        ];
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(FinancialTransaction::class, 'campaign_id');
    }

    public function acceptsTransactionDate(string $transactionDate): bool
    {
        return ($this->starts_on === null || $this->starts_on->format('Y-m-d') <= $transactionDate)
            && ($this->ends_on === null || $this->ends_on->format('Y-m-d') >= $transactionDate);
    }
}
