<?php

namespace App\Http\Controllers;

use App\Models\FinancialSmsLog;
use App\Services\SmsSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

class SwalaSmsWebhookController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $secret = SmsSettings::webhookSecret();
        $signature = strtolower(trim((string) $request->header('X-SwalaSMS-Signature')));

        if ($secret === '' || $signature === ''
            || ! hash_equals(hash_hmac('sha256', $request->getContent(), $secret), $signature)) {
            return response()->json(['success' => false], 401);
        }

        $payload = json_decode($request->getContent(), true);

        if (! is_array($payload) || ! is_string($payload['message_id'] ?? null) || $payload['message_id'] === '') {
            return response()->json(['success' => false], 422);
        }

        $status = $this->finalStatus($payload);

        if ($status === null) {
            return response()->json(['success' => true]);
        }

        $eventAt = is_numeric($payload['timestamp'] ?? null)
            ? Carbon::createFromTimestamp((int) $payload['timestamp'])
            : null;
        $deliveredAt = null;

        if (is_string($payload['delivered_at'] ?? null)) {
            try {
                $deliveredAt = Carbon::parse($payload['delivered_at']);
            } catch (Throwable) {
                $deliveredAt = null;
            }
        }

        $error = $payload['error'] ?? null;
        $reason = is_string($error) ? strip_tags($error) : null;

        DB::transaction(function () use ($payload, $status, $eventAt, $reason, $deliveredAt): void {
            $log = FinancialSmsLog::query()
                ->where('provider_message_id', $payload['message_id'])
                ->lockForUpdate()
                ->first();

            $log?->applyProviderStatus($status, $eventAt, $reason, $deliveredAt);
        });

        return response()->json(['success' => true]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function finalStatus(array $payload): ?string
    {
        $event = $payload['event'] ?? $payload['type'] ?? null;
        $candidate = match (true) {
            $event === 'sms.delivered' => 'delivered',
            $event === 'sms.failed' => 'failed',
            default => $payload['status'] ?? null,
        };

        return in_array($candidate, ['delivered', 'failed'], true) ? $candidate : null;
    }
}
