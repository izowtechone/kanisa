<?php

namespace App\Services;

use RuntimeException;

class SwalaSmsException extends RuntimeException
{
    public const NOT_ACCEPTED = 'DEFINITELY_NOT_ACCEPTED';

    public const UNKNOWN = 'UNKNOWN';

    public function __construct(
        string $message,
        public readonly ?int $httpStatus = null,
        public readonly ?string $errorCode = null,
        public readonly ?string $acceptanceState = null,
        public readonly bool $retryable = false,
        public readonly ?int $retryAfterSeconds = null,
    ) {
        parent::__construct($message);
    }

    public static function notConfigured(): self
    {
        return new self('SwalaSMS credentials or sender ID are not configured.', errorCode: 'NOT_CONFIGURED', acceptanceState: self::NOT_ACCEPTED);
    }

    public static function connection(): self
    {
        return new self('The SwalaSMS request could not be completed.', errorCode: 'CONNECTION_FAILED', acceptanceState: self::UNKNOWN, retryable: true);
    }

    public static function invalidResponse(int $httpStatus): self
    {
        return new self('SwalaSMS returned an unexpected response.', $httpStatus, 'INVALID_RESPONSE', self::UNKNOWN, true);
    }

    public function acceptanceIsUnknown(): bool
    {
        return $this->acceptanceState === self::UNKNOWN;
    }
}
