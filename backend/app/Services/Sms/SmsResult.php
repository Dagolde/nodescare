<?php

namespace App\Services\Sms;

class SmsResult
{
    public function __construct(
        public readonly bool $success,
        public readonly ?string $messageId = null,
        public readonly ?string $error = null,
    ) {}

    public static function ok(string $messageId): self
    {
        return new self(success: true, messageId: $messageId);
    }

    public static function fail(string $error): self
    {
        return new self(success: false, error: $error);
    }
}
