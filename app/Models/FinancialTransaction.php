<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class FinancialTransaction extends Model
{
    public const SOURCE_FINANCE = 'finance';

    public const SOURCE_SUNDAY_OFFERING = 'sunday_offering';

    public const TYPES = [
        'offering',
        'tithe',
        'contribution',
        'other_income',
        'expense',
    ];

    public const STATUSES = [
        'pending',
        'approved',
        'reversed',
        'voided',
    ];

    protected $fillable = [
        'transaction_number',
        'receipt_number',
        'source',
        'idempotency_key',
        'payload_hash',
        'type',
        'category_id',
        'campaign_id',
        'member_id',
        'member_number',
        'member_name',
        'created_by',
        'envelope_code',
        'payee',
        'amount',
        'currency',
        'payment_method',
        'reference_number',
        'status',
        'is_anonymous',
        'ledger_sign',
        'transaction_date',
        'description',
        'notes',
        'reversal_of_id',
        'approved_by',
        'reversed_by',
        'approved_at',
        'reversed_at',
        'voided_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'is_anonymous' => 'boolean',
            'transaction_date' => 'date',
            'approved_at' => 'datetime',
            'reversed_at' => 'datetime',
            'voided_at' => 'datetime',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(FinanceCategory::class, 'category_id');
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(FinancialCampaign::class, 'campaign_id');
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function reverser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reversed_by');
    }

    public function reversalOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversal_of_id');
    }

    public function reversals(): HasMany
    {
        return $this->hasMany(self::class, 'reversal_of_id');
    }

    public function auditEvents(): HasMany
    {
        return $this->hasMany(FinancialTransactionAudit::class);
    }

    public function smsLog(): HasOne
    {
        return $this->hasOne(FinancialSmsLog::class);
    }
}
