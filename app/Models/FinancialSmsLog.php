<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FinancialSmsLog extends Model
{
    protected $hidden = [
        'message',
        'phone',
        'idempotency_key',
        'provider_batch_key',
    ];

    protected $fillable = [
        'financial_transaction_id',
        'bulk_sms_campaign_id',
        'member_id',
        'user_id',
        'notification_type',
        'message',
        'created_by',
        'phone',
        'status',
        'is_test',
        'provider_message_id',
        'attempts',
        'sms_segments',
        'error_message',
        'accepted_at',
        'delivery_status',
        'delivery_checked_at',
        'provider',
        'sender_id',
        'encoding',
        'idempotency_key',
        'provider_batch_key',
        'provider_campaign_uid',
        'provider_status',
        'provider_error_code',
        'provider_acceptance_state',
        'provider_units',
        'provider_failure_reason',
        'provider_event_at',
        'delivered_at',
    ];

    protected function casts(): array
    {
        return [
            'is_test' => 'boolean',
            'accepted_at' => 'datetime',
            'message' => 'encrypted',
            'delivery_checked_at' => 'datetime',
            'provider_event_at' => 'datetime',
            'delivered_at' => 'datetime',
        ];
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(FinancialTransaction::class, 'financial_transaction_id');
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(BulkSmsCampaign::class, 'bulk_sms_campaign_id');
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function smsAttempts(): HasMany
    {
        return $this->hasMany(FinancialSmsAttempt::class, 'financial_sms_log_id')
            ->orderBy('attempt_number');
    }

    public function statusEvents(): HasMany
    {
        return $this->hasMany(FinancialSmsStatusEvent::class, 'financial_sms_log_id')
            ->latest('id');
    }

    public function isFinalDelivery(): bool
    {
        return in_array($this->delivery_status, ['delivered', 'failed'], true);
    }

    public function recordStatusEvent(
        ?string $previousStatus,
        string $status,
        string $source,
        ?string $failureReason = null,
        ?CarbonInterface $eventAt = null,
    ): void {
        FinancialSmsStatusEvent::query()->create([
            'financial_sms_log_id' => $this->id,
            'previous_status' => $previousStatus,
            'status' => $status,
            'source' => $source,
            'failure_reason' => $status === 'failed' ? mb_substr((string) $failureReason, 0, 255) : null,
            'provider_event_at' => $eventAt ?? now(),
        ]);
    }

    /**
     * Applies a provider status idempotently: repeats and older events are ignored, and a
     * non-final status never replaces a final one. Returns whether anything changed.
     */
    public function applyProviderStatus(
        string $status,
        ?CarbonInterface $eventAt = null,
        ?string $failureReason = null,
        ?CarbonInterface $deliveredAt = null,
        string $source = 'poll',
    ): bool {
        $isFinal = in_array($status, ['delivered', 'failed'], true);
        $timezone = config('app.timezone');
        $eventAt = $eventAt?->copy()->setTimezone($timezone);
        $deliveredAt = $deliveredAt?->copy()->setTimezone($timezone);

        if ($this->isFinalDelivery()) {
            $isNewer = $eventAt !== null
                && ($this->provider_event_at === null || $eventAt->greaterThan($this->provider_event_at));

            if (! $isFinal || $status === $this->delivery_status || ! $isNewer) {
                return false;
            }
        } elseif ($eventAt !== null && $this->provider_event_at !== null && $eventAt->lessThan($this->provider_event_at)) {
            return false;
        } elseif ($status === $this->delivery_status && ! $isFinal) {
            return false;
        }

        $previousStatus = $this->delivery_status;
        $this->forceFill([
            'provider_status' => $status,
            'delivery_status' => $status,
            'delivery_checked_at' => now(),
            'provider_event_at' => $eventAt ?? $this->provider_event_at,
            'delivered_at' => $status === 'delivered' ? ($deliveredAt ?? $eventAt ?? now()) : null,
            'provider_failure_reason' => $status === 'failed' ? mb_substr((string) $failureReason, 0, 255) : null,
        ])->save();

        $this->recordStatusEvent($previousStatus, $status, $source, $failureReason, $eventAt);

        return true;
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
