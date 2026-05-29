<?php

namespace App\Contracts;

use App\Services\Sms\SmsResult;

interface SmsGateway
{
    /**
     * Send an SMS message to the given phone number.
     */
    public function send(string $to, string $message): SmsResult;
}
