<?php

namespace App\Services;

use App\Models\FinancialSetting;
use App\Models\FinancialSmsLog;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class BeemSmsGateway
{
    public function __construct(
        private readonly FinancialSmsMessageBuilder $messages,
    ) {}

    public function send(FinancialSmsLog $log): string
    {
        $apiKey = FinancialSetting::getValue('beem_api_key');
        $apiSecret = FinancialSetting::getValue('beem_api_secret');
        $senderId = trim((string) FinancialSetting::getValue('beem_sender_id'));

        if ($apiKey === null || $apiSecret === null || $senderId === '') {
            throw new RuntimeException('Beem SMS settings are incomplete.');
        }
        $transaction = $log->transaction;
        $message = $log->message
            ?? ($transaction === null ? null : $this->messages->forTransaction($transaction));

        if ($message === null || trim($message) === '') {
            throw new RuntimeException('Beem SMS message content is missing.');
        }

        try {
            $response = Http::connectTimeout(3)
                ->timeout(10)
                ->withBasicAuth($apiKey, $apiSecret)
                ->post('https://apisms.beem.africa/v1/send', [
                    'source_addr' => $senderId,
                    'message' => $message,
                    'encoding' => 0,
                    'schedule_time' => '',
                    'recipients' => [
                        [
                            'recipient_id' => 1,
                            'dest_addr' => $this->normalizePhone($log->phone),
                        ],
                    ],
                ]);
        } catch (ConnectionException) {
            throw new RuntimeException('Beem SMS request could not be completed.');
        }

        if (! $response->successful()) {
            throw new RuntimeException('Beem SMS request failed with HTTP status '.$response->status().'.');
        }

        $responseData = $response->json();

        if (! is_array($responseData)
            || ($responseData['successful'] ?? false) !== true
            || ($responseData['code'] ?? null) !== 100
            || ($responseData['valid'] ?? 0) < 1
            || ! is_string($responseData['request_id'] ?? null)
            || $responseData['request_id'] === '') {
            throw new RuntimeException('Beem did not accept the SMS request.');
        }

        return $responseData['request_id'];
    }

    /**
     * @return array{credit_balance: int|float|string}
     */
    public function balance(): array
    {
        $apiKey = FinancialSetting::getValue('beem_api_key');
        $apiSecret = FinancialSetting::getValue('beem_api_secret');

        if ($apiKey === null || $apiSecret === null) {
            throw new RuntimeException('Beem API credentials are not configured.');
        }

        try {
            $response = Http::connectTimeout(3)
                ->timeout(10)
                ->withBasicAuth($apiKey, $apiSecret)
                ->get('https://apisms.beem.africa/public/v1/vendors/balance');
        } catch (ConnectionException) {
            throw new RuntimeException('Beem balance request could not be completed.');
        }

        if (! $response->successful()) {
            throw new RuntimeException('Beem balance request failed with HTTP status '.$response->status().'.');
        }

        $balance = $response->json('data.credit_balance');

        if (! is_numeric($balance)) {
            throw new RuntimeException('Beem returned an invalid SMS balance response.');
        }

        return ['credit_balance' => $balance];
    }

    public function deliveryReport(FinancialSmsLog $log): ?string
    {
        $apiKey = FinancialSetting::getValue('beem_api_key');
        $apiSecret = FinancialSetting::getValue('beem_api_secret');

        if ($apiKey === null || $apiSecret === null || $log->provider_message_id === null) {
            throw new RuntimeException('Beem delivery report settings are incomplete.');
        }

        try {
            $response = Http::connectTimeout(3)
                ->timeout(10)
                ->withBasicAuth($apiKey, $apiSecret)
                ->get('https://dlrapi.beem.africa/public/v1/delivery-reports', [
                    'dest_addr' => $this->normalizePhone($log->phone),
                    'request_id' => $log->provider_message_id,
                ]);
        } catch (ConnectionException) {
            throw new RuntimeException('Beem delivery report request could not be completed.');
        }

        if ($response->notFound()) {
            return null;
        }

        if (! $response->successful()) {
            throw new RuntimeException('Beem delivery report request failed with HTTP status '.$response->status().'.');
        }

        $reports = $response->json();
        $data = is_array($reports) ? ($reports['data'] ?? $reports) : null;
        $report = is_array($data) && array_is_list($data) ? ($data[0] ?? null) : $data;
        $status = is_array($report)
            ? strtoupper((string) ($report['status'] ?? $report['delivery_status'] ?? ''))
            : '';

        return in_array($status, ['PENDING', 'DELIVERED', 'UNDELIVERED'], true) ? $status : null;
    }

    public function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone);

        if ($digits === null) {
            throw new RuntimeException('The member phone number is invalid.');
        }

        if (str_starts_with($digits, '0')) {
            $digits = '255'.substr($digits, 1);
        } elseif (strlen($digits) === 9) {
            $digits = '255'.$digits;
        }

        if (! preg_match('/^[1-9]\d{7,14}$/', $digits)) {
            throw new RuntimeException('The member phone number is invalid.');
        }

        return $digits;
    }
}
