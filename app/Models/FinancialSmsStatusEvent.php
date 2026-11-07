<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinancialSmsStatusEvent extends Model
{
    protected $fillable = [
        'financial_sms_log_id',
        'previous_status',
        'status',
        'source',
        'failure_reason',
        'provider_event_at',
    ];

    protected function casts(): array
    {
        return [
            'provider_event_at' => 'datetime',
        ];
    }

    public function smsLog(): BelongsTo
    {
        return $this->belongsTo(FinancialSmsLog::class, 'financial_sms_log_id');
    }
}
