<?php

namespace App\Services;

use App\Jobs\SendFinancialSms;
use App\Models\FinanceCategory;
use App\Models\FinancialCampaign;
use App\Models\FinancialSetting;
use App\Models\FinancialSmsLog;
use App\Models\FinancialTransaction;
use App\Models\FinancialTransactionAudit;
use App\Models\Member;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class FinancialTransactionService
{
    public function __construct(
        private readonly FinancialNumberGenerator $numbers,
        private readonly FinancialSmsMessageBuilder $smsMessages,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes, User $actor): FinancialTransaction
    {
        $attributes['amount'] = $this->normalizeAmount((string) $attributes['amount']);
        if (! in_array($attributes['type'], FinancialTransaction::TYPES, true)
            || ! preg_match('/^(?=.*[1-9])\d{1,13}(?:\.\d{1,2})$/', $attributes['amount'])) {
            throw ValidationException::withMessages([
                'amount' => __('finance.import_error_amount'),
            ]);
        }
        $payloadHash = hash('sha256', json_encode([
            'type' => $attributes['type'],
            'category_id' => (int) $attributes['category_id'],
            'campaign_id' => isset($attributes['campaign_id']) ? (int) $attributes['campaign_id'] : null,
            'member_id' => isset($attributes['member_id']) ? (int) $attributes['member_id'] : null,
            'envelope_code' => $attributes['envelope_code'] ?? null,
            'payee' => $attributes['payee'] ?? null,
            'amount' => $attributes['amount'],
            'payment_method' => $attributes['payment_method'],
            'reference_number' => $attributes['reference_number'] ?? null,
            'transaction_date' => $attributes['transaction_date'],
            'description' => $attributes['description'] ?? null,
            'notes' => $attributes['notes'] ?? null,
            'is_anonymous' => (bool) ($attributes['is_anonymous'] ?? false),
        ], JSON_THROW_ON_ERROR));

        try {
            return DB::transaction(function () use ($attributes, $actor, $payloadHash): FinancialTransaction {
                $existing = FinancialTransaction::query()
                    ->where('idempotency_key', $attributes['idempotency_key'])
                    ->first();

                if ($existing !== null) {
                    if (! hash_equals($existing->payload_hash, $payloadHash)) {
                        throw ValidationException::withMessages([
                            'idempotency_key' => __('finance.idempotency_conflict'),
                        ]);
                    }

                    return $existing;
                }

                $transactionDate = (string) $attributes['transaction_date'];
                $categoryIsActive = FinanceCategory::query()
                    ->whereKey($attributes['category_id'])
                    ->where('type', $attributes['type'])
                    ->where('is_active', true)
                    ->lockForUpdate()
                    ->exists();

                if (! $categoryIsActive) {
                    throw ValidationException::withMessages([
                        'category_id' => __('finance.category_type_mismatch'),
                    ]);
                }

                if ($attributes['type'] === 'contribution') {
                    $campaign = FinancialCampaign::query()
                        ->whereKey($attributes['campaign_id'] ?? null)
                        ->where('status', 'active')
                        ->lockForUpdate()
                        ->first();

                    if ($campaign === null) {
                        throw ValidationException::withMessages([
                            'campaign_id' => __('finance.campaign_not_active'),
                        ]);
                    }

                    if (! $campaign->acceptsTransactionDate($transactionDate)) {
                        throw ValidationException::withMessages([
                            'transaction_date' => __('finance.campaign_date_outside_window'),
                        ]);
                    }
                }

                $member = null;

                if (isset($attributes['member_id'])) {
                    $member = Member::query()
                        ->whereKey($attributes['member_id'])
                        ->where('status', 'active')
                        ->lockForUpdate()
                        ->first();

                    if ($member === null) {
                        throw ValidationException::withMessages([
                            'member_id' => __('finance.active_member_required'),
                        ]);
                    }
                }

                $transaction = FinancialTransaction::query()->create([
                    ...$attributes,
                    'transaction_number' => $this->numbers->next('transaction', $transactionDate, 'KKKT-TXN'),
                    'receipt_number' => $this->numbers->next(
                        'receipt',
                        $transactionDate,
                        FinancialSetting::getValue('receipt_prefix', 'KKKT-RCPT'),
                    ),
                    'payload_hash' => $payloadHash,
                    'created_by' => $actor->id,
                    'member_number' => $member?->membership_number,
                    'member_name' => $member === null ? null : trim(implode(' ', array_filter([
                        $member->first_name,
                        $member->middle_name,
                        $member->last_name,
                    ]))),
                    'currency' => FinancialSetting::getValue(
                        'currency',
                        Setting::getValue('currency', 'TZS'),
                    ),
                    'status' => 'pending',
                    'ledger_sign' => 1,
                ]);

                $this->audit($transaction, $actor, 'created', [
                    'status' => $transaction->status,
                    'amount' => $transaction->amount,
                ]);

                return $transaction;
            });
        } catch (QueryException $exception) {
            $existing = FinancialTransaction::query()
                ->where('idempotency_key', $attributes['idempotency_key'])
                ->first();

            if ($existing === null) {
                throw $exception;
            }

            if (! hash_equals($existing->payload_hash, $payloadHash)) {
                throw ValidationException::withMessages([
                    'idempotency_key' => __('finance.idempotency_conflict'),
                ]);
            }

            return $existing;
        }
    }

    public function approve(FinancialTransaction $transaction, User $actor): FinancialTransaction
    {
        $approved = DB::transaction(function () use ($transaction, $actor): FinancialTransaction {
            $locked = FinancialTransaction::query()->lockForUpdate()->findOrFail($transaction->id);

            if ($locked->status !== 'pending') {
                throw ValidationException::withMessages([
                    'transaction' => __('finance.transaction_not_pending'),
                ]);
            }

            if ($locked->created_by === $actor->id) {
                throw ValidationException::withMessages([
                    'transaction' => __('finance.separation_of_duties'),
                ]);
            }

            $locked->forceFill([
                'status' => 'approved',
                'approved_by' => $actor->id,
                'approved_at' => now(),
            ])->save();
            $this->audit($locked, $actor, 'approved');

            if ($locked->reversal_of_id !== null) {
                $original = FinancialTransaction::query()
                    ->lockForUpdate()
                    ->findOrFail($locked->reversal_of_id);

                if ($original->status !== 'approved') {
                    throw ValidationException::withMessages([
                        'transaction' => __('finance.only_approved_can_reverse'),
                    ]);
                }

                $original->forceFill([
                    'status' => 'reversed',
                    'reversed_by' => $actor->id,
                    'reversed_at' => now(),
                ])->save();
                $this->audit($original, $actor, 'reversed', [
                    'reversal_transaction_id' => $locked->id,
                ]);
            }

            return $locked;
        });

        $this->queueConfirmationSms($approved);

        return $approved;
    }

    public function reverse(FinancialTransaction $transaction, User $actor, string $reason): FinancialTransaction
    {
        return DB::transaction(function () use ($transaction, $actor, $reason): FinancialTransaction {
            $original = FinancialTransaction::query()->lockForUpdate()->findOrFail($transaction->id);

            if ($original->status !== 'approved') {
                throw ValidationException::withMessages([
                    'transaction' => __('finance.only_approved_can_reverse'),
                ]);
            }

            if ($original->reversals()->exists()) {
                throw ValidationException::withMessages([
                    'transaction' => __('finance.reversal_already_pending'),
                ]);
            }

            if ($original->created_by === $actor->id || $original->approved_by === $actor->id) {
                throw ValidationException::withMessages([
                    'transaction' => __('finance.separation_of_duties'),
                ]);
            }

            $reversalDate = now()->toDateString();
            $reversal = FinancialTransaction::query()->create([
                'transaction_number' => $this->numbers->next('transaction', $reversalDate, 'KKKT-TXN'),
                'receipt_number' => $this->numbers->next(
                    'receipt',
                    $reversalDate,
                    FinancialSetting::getValue('receipt_prefix', 'KKKT-RCPT'),
                ),
                'idempotency_key' => (string) Str::uuid(),
                'payload_hash' => hash('sha256', 'reversal:'.$original->id),
                'type' => $original->type,
                'category_id' => $original->category_id,
                'campaign_id' => $original->campaign_id,
                'member_id' => $original->member_id,
                'member_number' => $original->member_number,
                'member_name' => $original->member_name,
                'created_by' => $actor->id,
                'reversal_of_id' => $original->id,
                'envelope_code' => $original->envelope_code,
                'payee' => $original->payee,
                'amount' => $original->amount,
                'currency' => $original->currency,
                'payment_method' => $original->payment_method,
                'reference_number' => $original->reference_number,
                'status' => 'pending',
                'is_anonymous' => $original->is_anonymous,
                'ledger_sign' => -1,
                'transaction_date' => $reversalDate,
                'description' => __('finance.reversal_description', ['number' => $original->transaction_number]),
                'notes' => $reason,
            ]);

            $this->audit($original, $actor, 'reversal_requested', [
                'reversal_transaction_id' => $reversal->id,
                'reason' => $reason,
            ]);
            $this->audit($reversal, $actor, 'created_reversal', [
                'original_transaction_id' => $original->id,
                'reason' => $reason,
            ]);

            return $reversal;
        });
    }

    public function void(FinancialTransaction $transaction, User $actor, string $reason): FinancialTransaction
    {
        return DB::transaction(function () use ($transaction, $actor, $reason): FinancialTransaction {
            $locked = FinancialTransaction::query()->lockForUpdate()->findOrFail($transaction->id);

            if ($locked->status !== 'pending') {
                throw ValidationException::withMessages([
                    'transaction' => __('finance.transaction_not_pending'),
                ]);
            }

            $locked->forceFill([
                'status' => 'voided',
                'voided_at' => now(),
            ])->save();
            $this->audit($locked, $actor, 'voided', [
                'reason' => $reason,
            ]);

            return $locked;
        });
    }

    /**
     * @param  array<string, mixed>  $details
     */
    private function audit(FinancialTransaction $transaction, User $actor, string $action, array $details = []): void
    {
        FinancialTransactionAudit::query()->create([
            'financial_transaction_id' => $transaction->id,
            'user_id' => $actor->id,
            'actor_name' => $actor->name,
            'action' => $action,
            'details' => $details,
        ]);
    }

    private function queueConfirmationSms(FinancialTransaction $transaction): void
    {
        if ($transaction->reversal_of_id !== null || $transaction->member_id === null) {
            return;
        }

        if (FinancialSetting::getValue('beem_enabled', '0') !== '1') {
            return;
        }

        $automaticNotificationKey = match ($transaction->type) {
            'offering', 'tithe' => 'beem_auto_offering',
            'contribution' => 'beem_auto_contribution',
            'other_income' => 'beem_auto_other_income',
            default => null,
        };

        if ($automaticNotificationKey === null
            || FinancialSetting::getValue($automaticNotificationKey, '0') !== '1') {
            return;
        }

        $phone = Member::query()->whereKey($transaction->member_id)->value('phone');

        if (! is_string($phone) || trim($phone) === '') {
            FinancialSmsLog::query()->create([
                'financial_transaction_id' => $transaction->id,
                'phone' => '',
                'status' => 'no_phone',
            ]);

            return;
        }

        $testMode = FinancialSetting::getValue('beem_test_mode', '1') === '1';
        $hasCredentials = trim((string) FinancialSetting::getValue('beem_api_key')) !== ''
            && trim((string) FinancialSetting::getValue('beem_api_secret')) !== ''
            && trim((string) FinancialSetting::getValue('beem_sender_id')) !== '';

        $log = FinancialSmsLog::query()->firstOrCreate([
            'financial_transaction_id' => $transaction->id,
            'notification_type' => 'financial_receipt',
        ], [
            'phone' => $phone,
            'message' => $this->smsMessages->forTransaction($transaction),
            'is_test' => $testMode,
            'status' => $testMode || $hasCredentials ? 'queued' : 'not_configured',
        ]);

        if ($log->wasRecentlyCreated && ($testMode || $hasCredentials)) {
            try {
                SendFinancialSms::dispatch($log->id);
            } catch (\Throwable) {
                $log->forceFill([
                    'status' => 'failed',
                    'error_message' => 'The SMS could not be added to the delivery queue.',
                ])->save();
            }
        }
    }

    private function normalizeAmount(string $amount): string
    {
        [$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, '');
        $whole = ltrim($whole, '0');

        return ($whole === '' ? '0' : $whole).'.'.str_pad($fraction, 2, '0');
    }
}
