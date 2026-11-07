<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use RuntimeException;

class SwalaSmsService
{
    public const MAX_BULK_RECIPIENTS = 1000;

    public function __construct(private readonly MobilePhoneNumber $phones) {}

    /**
     * @var list<string>
     */
    public const MESSAGE_STATUSES = ['queued', 'processing', 'sent', 'delivered', 'failed', 'scheduled'];

    /**
     * Sends one SMS. A 202 response means the provider queued it, not that the handset received it.
     *
     * @return array{uid: string, campaign_uid: string, status: string, units: ?int, segments: ?int, sent_at: ?string, delivered_at: ?string, failure_reason: ?string, replay: bool}
     */
    public function sendMessage(string $phone, string $message, string $idempotencyKey): array
    {
        try {
            $recipient = $this->toE164($phone);
        } catch (RuntimeException) {
            throw new SwalaSmsException(
                'The recipient phone number is invalid or not a mobile number.',
                errorCode: 'INVALID_RECIPIENT',
                acceptanceState: SwalaSmsException::NOT_ACCEPTED,
            );
        }

        $result = $this->sendBulk([['to' => $recipient]], $message, 'KKKT CMS Notifications', $idempotencyKey);
        $digits = $this->digits($recipient);

        if (isset($result['failures'][$digits])) {
            throw new SwalaSmsException(
                $result['failures'][$digits],
                errorCode: 'RECIPIENT_REJECTED',
                acceptanceState: SwalaSmsException::NOT_ACCEPTED,
            );
        }

        $messageResult = $result['messages'][$digits] ?? null;
        if ($messageResult === null) {
            throw SwalaSmsException::invalidResponse(202);
        }

        return [
            'uid' => $messageResult['uid'],
            'campaign_uid' => $result['campaign_uid'],
            'status' => $messageResult['status'],
            'units' => $messageResult['units'],
            'segments' => null,
            'sent_at' => null,
            'delivered_at' => null,
            'failure_reason' => null,
            'replay' => $result['replay'],
        ];
    }

    /**
     * Sends one campaign batch (at most 1000 recipients) with a single idempotency key.
     * Each recipient is an E.164 number plus optional tag values used by `{tag}` placeholders.
     *
     * @param  list<array{to: string, tags?: array<string, string>}>  $recipients
     * @return array{campaign_uid: string, messages: array<string, array{uid: string, status: string, units: ?int}>, failures: array<string, string>, replay: bool}
     */
    public function sendBulk(array $recipients, string $message, ?string $campaignName, string $idempotencyKey): array
    {
        if ($recipients === [] || count($recipients) > self::MAX_BULK_RECIPIENTS) {
            throw new InvalidArgumentException('A SwalaSMS bulk request needs between 1 and 1000 recipients.');
        }

        $payloadRecipients = array_map(function (array $recipient): string|array {
            $tags = array_filter($recipient['tags'] ?? [], fn ($value): bool => is_string($value));

            $phone = $this->toE164($recipient['to']);

            return $tags === [] ? $phone : ['to' => $phone] + $tags;
        }, $recipients);

        $payload = [
            'recipients' => $payloadRecipients,
            'message' => $message,
            'sender_id' => $this->senderId(),
        ];

        if ($campaignName !== null && trim($campaignName) !== '') {
            $payload['campaign_name'] = mb_substr(trim($campaignName), 0, 100);
        }

        $response = $this->send('post', '/v2/sms/send-bulk', $payload, ['Idempotency-Key' => $idempotencyKey]);
        $data = $response->json('data');

        if (! is_array($data) || ! is_string($data['uid'] ?? null) || $data['uid'] === ''
            || ! is_array($data['messages'] ?? null) || ! is_array($data['failures'] ?? null)) {
            throw SwalaSmsException::invalidResponse($response->status());
        }

        $messages = [];

        foreach ($data['messages'] as $entry) {
            if (! is_array($entry) || ! is_string($entry['to'] ?? null)
                || ! is_string($entry['message_uid'] ?? null) || $entry['message_uid'] === ''
                || ! in_array($entry['status'] ?? null, self::MESSAGE_STATUSES, true)) {
                throw SwalaSmsException::invalidResponse($response->status());
            }

            $messages[$this->digits($entry['to'])] = [
                'uid' => $entry['message_uid'],
                'status' => $entry['status'],
                'units' => isset($entry['units']) && is_numeric($entry['units']) ? (int) $entry['units'] : null,
            ];
        }

        $failures = [];

        foreach ($data['failures'] as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $to = $entry['to'] ?? $entry['recipient'] ?? null;
            $reason = $entry['reason'] ?? $entry['error'] ?? $entry['message'] ?? 'rejected';

            if (is_string($to)) {
                $failures[$this->digits($to)] = $this->sanitize(is_string($reason) ? $reason : 'rejected');
            }
        }

        return [
            'campaign_uid' => $data['uid'],
            'messages' => $messages,
            'failures' => $failures,
            'replay' => ($data['idempotent_replay'] ?? false) === true,
        ];
    }

    /**
     * @return array{uid: string, status: string, units: ?int, segments: ?int, sent_at: ?string, delivered_at: ?string, failure_reason: ?string, replay: bool}
     */
    public function messageStatus(string $uid): array
    {
        $response = $this->send('get', '/v1/sms/messages/'.rawurlencode($uid));
        $data = $response->json('data');

        if (! is_array($data)) {
            throw SwalaSmsException::invalidResponse($response->status());
        }

        return $this->normalizeMessage($data, $response->status()) + ['replay' => false];
    }

    /**
     * @return array{balance: int|float, currency: string, countries: list<array<string, mixed>>}
     */
    public function balance(): array
    {
        $response = $this->send('get', '/v1/balance');
        $data = $response->json('data');

        if (! is_array($data) || ! is_numeric($data['balance'] ?? null)) {
            throw SwalaSmsException::invalidResponse($response->status());
        }

        return [
            'balance' => $data['balance'] + 0,
            'currency' => is_string($data['currency'] ?? null) ? $data['currency'] : 'SMS',
            'countries' => array_values(array_filter((array) ($data['countries'] ?? []), 'is_array')),
        ];
    }

    /**
     * @return list<array{sender_id: string, status: string, is_usable: bool, is_default: ?bool, country_code: ?string, country_name: ?string, purpose: ?string, updated_at: ?string}>
     */
    public function senderIds(string $apiKey): array
    {
        $response = $this->send('get', '/v1/sender-ids', apiKey: $apiKey);
        $entries = $response->json('data');

        if ($response->status() !== 200 || $response->json('success') !== true
            || ! is_array($entries) || ! array_is_list($entries)) {
            throw SwalaSmsException::invalidResponse($response->status());
        }

        $senders = [];
        foreach ($entries as $entry) {
            if (! is_array($entry) || ! is_string($entry['sender_id'] ?? null)
                || trim($entry['sender_id']) === '' || ! is_string($entry['status'] ?? null)
                || trim($entry['status']) === '' || ! is_bool($entry['is_usable'] ?? null)) {
                throw SwalaSmsException::invalidResponse($response->status());
            }

            foreach (['country_code', 'country_name', 'purpose', 'updated_at'] as $field) {
                if (isset($entry[$field]) && ! is_string($entry[$field])) {
                    throw SwalaSmsException::invalidResponse($response->status());
                }
            }

            if (isset($entry['is_default']) && ! is_bool($entry['is_default'])) {
                throw SwalaSmsException::invalidResponse($response->status());
            }

            $senders[] = [
                'sender_id' => $entry['sender_id'],
                'status' => $entry['status'],
                'is_usable' => $entry['is_usable'],
                'is_default' => $entry['is_default'] ?? null,
                'country_code' => $entry['country_code'] ?? null,
                'country_name' => $entry['country_name'] ?? null,
                'purpose' => $entry['purpose'] ?? null,
                'updated_at' => $entry['updated_at'] ?? null,
            ];
        }

        return $senders;
    }

    /**
     * Returns the digits-only phone format used for storage and comparison.
     */
    public function normalizePhone(string $phone): string
    {
        return substr($this->phones->normalize($phone), 1);
    }

    public function toE164(string $phone): string
    {
        return $this->phones->normalize($phone);
    }

    private function senderId(): string
    {
        $senderId = SmsSettings::senderId();

        if ($senderId === '') {
            throw SwalaSmsException::notConfigured();
        }

        return $senderId;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, string>  $headers
     */
    private function send(string $method, string $path, array $payload = [], array $headers = [], ?string $apiKey = null): Response
    {
        $apiKey ??= SmsSettings::apiKey();

        if ($apiKey === '') {
            throw SwalaSmsException::notConfigured();
        }

        try {
            $request = Http::baseUrl(rtrim((string) config('services.swalasms.base_url'), '/'))
                ->acceptJson()
                ->withToken($apiKey)
                ->withHeaders($headers)
                ->connectTimeout(5)
                ->timeout(SmsSettings::timeout());

            $response = $method === 'post'
                ? $request->asJson()->post($path, $payload)
                : $request->get($path, $payload);
        } catch (ConnectionException) {
            throw SwalaSmsException::connection();
        }

        if ($response->successful() && $response->json('success') !== false) {
            return $response;
        }

        throw $this->errorFrom($response);
    }

    private function errorFrom(Response $response): SwalaSmsException
    {
        $status = $response->status();
        $code = $response->json('error_code');
        $code = is_string($code) ? mb_substr($code, 0, 60) : null;
        $state = $response->json('acceptance_state');
        $state = is_string($state) ? $state : null;
        $retryable = $status === 429 || $status === 408 || $status >= 500;

        if ($status === 409) {
            $state = SwalaSmsException::UNKNOWN;
        } elseif ($state === null) {
            $state = $status >= 400 && $status < 500 && $status !== 408
                ? SwalaSmsException::NOT_ACCEPTED
                : SwalaSmsException::UNKNOWN;
        }

        $retryAfter = $response->header('Retry-After');
        $retryAfter = is_numeric($retryAfter) ? max(1, min(900, (int) $retryAfter)) : null;
        $message = $response->json('message');

        return new SwalaSmsException(
            $this->sanitize(is_string($message) ? $message : 'SwalaSMS rejected the request.'),
            $status,
            $code,
            $state,
            $retryable,
            $retryAfter,
        );
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{uid: string, status: string, units: ?int, segments: ?int, sent_at: ?string, delivered_at: ?string, failure_reason: ?string}
     */
    private function normalizeMessage(array $data, int $httpStatus): array
    {
        if (! is_string($data['uid'] ?? null) || $data['uid'] === ''
            || ! in_array($data['status'] ?? null, self::MESSAGE_STATUSES, true)) {
            throw SwalaSmsException::invalidResponse($httpStatus);
        }

        $segments = $data['encoding']['segments'] ?? null;
        $failureReason = $data['failure_reason'] ?? null;

        return [
            'uid' => $data['uid'],
            'status' => $data['status'],
            'units' => is_numeric($data['units'] ?? null) ? (int) $data['units'] : null,
            'segments' => is_numeric($segments) ? (int) $segments : null,
            'sent_at' => is_string($data['sent_at'] ?? null) ? $data['sent_at'] : null,
            'delivered_at' => is_string($data['delivered_at'] ?? null) ? $data['delivered_at'] : null,
            'failure_reason' => is_string($failureReason) ? $this->sanitize($failureReason) : null,
        ];
    }

    private function digits(string $phone): string
    {
        return preg_replace('/\D+/', '', $phone) ?? '';
    }

    /**
     * Provider text is shown to administrators, so keep it short and strip anything key-shaped.
     */
    private function sanitize(string $text): string
    {
        $text = preg_replace('/swl_(?:test|live)_\S+/i', '[redacted]', strip_tags($text)) ?? '';
        $text = preg_replace('/Bearer\s+\S+/i', 'Bearer [redacted]', $text) ?? '';

        return mb_substr(trim(preg_replace('/\s+/', ' ', $text) ?? ''), 0, 200);
    }
}
