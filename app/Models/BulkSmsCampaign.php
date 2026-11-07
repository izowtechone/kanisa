<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BulkSmsCampaign extends Model
{
    protected $fillable = [
        'uuid',
        'name',
        'message',
        'language',
        'status',
        'selection_mode',
        'selection_criteria',
        'recipient_count',
        'invalid_phone_count',
        'sms_segments',
        'created_by',
        'scheduled_at',
        'started_at',
        'completed_at',
        'cancelled_at',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'message' => 'encrypted',
            'selection_criteria' => 'array',
            'scheduled_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(FinancialSmsLog::class, 'bulk_sms_campaign_id');
    }

    public function refreshStatus(): void
    {
        if ($this->status === 'cancelled') {
            return;
        }

        $total = $this->recipients()->count();
        $pending = $this->recipients()->whereIn('status', ['queued', 'sending'])->count();
        $sending = $this->recipients()->where('status', 'sending')->exists();
        $accepted = $this->recipients()->where('status', 'accepted')->count();
        $failed = $this->recipients()->where('status', 'failed')->count();

        if ($pending > 0) {
            $status = $sending ? 'processing' : ($this->scheduled_at?->isFuture() ? 'scheduled' : 'queued');
        } elseif ($total === 0 || $accepted === 0) {
            $status = 'failed';
        } elseif ($failed > 0) {
            $status = 'partially_failed';
        } else {
            $status = 'completed';
        }

        $this->forceFill([
            'status' => $status,
            'completed_at' => in_array($status, ['completed', 'partially_failed', 'failed'], true)
                ? ($this->completed_at ?? now())
                : null,
        ])->save();
    }
}
