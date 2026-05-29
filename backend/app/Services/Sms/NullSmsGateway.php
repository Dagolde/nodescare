<?php

namespace App\Services\Sms;

use App\Contracts\SmsGateway;

/**
 * No-op SMS gateway for testing environments.
 * Always returns a successful result without making any HTTP calls.
 */
class NullSmsGateway implements SmsGateway
{
    public function send(string $to, string $message): SmsResult
    {
        return SmsResult::ok('null-' . uniqid());
    }
}
