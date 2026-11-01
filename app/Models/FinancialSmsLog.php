<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FinancialSmsLog extends Model
{
    protected $hidden = [
        'message',
        'phone',
    ];

    protected $fillable = [
        'financial_transaction_id',
        'notification_type',
        'message',
        'created_by',
        'phone',
        'status',
        'is_test',
        'provider_message_id',
        'attempts',
        'error_message',
        'accepted_at',
        'delivery_status',
        'delivery_checked_at',
    ];

    protected function casts(): array
    {
        return [
            'is_test' => 'boolean',
            'accepted_at' => 'datetime',
            'message' => 'encrypted',
            'delivery_checked_at' => 'datetime',
        ];
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(FinancialTransaction::class, 'financial_transaction_id');
    }

    public function smsAttempts(): HasMany
    {
        return $this->hasMany(FinancialSmsAttempt::class, 'financial_sms_log_id')
            ->orderBy('attempt_number');
    }

    public function maskedPhone(): string
    {
        $digits = preg_replace('/\D+/', '', $this->phone) ?? '';

        if ($digits === '') {
            return '—';
        }

        if (strlen($digits) < 6) {
            return str_repeat('*', strlen($digits));
        }

        return substr($digits, 0, 3).str_repeat('*', strlen($digits) - 5).substr($digits, -2);
    }
}
