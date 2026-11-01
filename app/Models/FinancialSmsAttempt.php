<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinancialSmsAttempt extends Model
{
    protected $fillable = [
        'financial_sms_log_id',
        'attempt_number',
        'status',
        'provider_message_id',
        'error_message',
        'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function smsLog(): BelongsTo
    {
        return $this->belongsTo(FinancialSmsLog::class, 'financial_sms_log_id');
    }
}
