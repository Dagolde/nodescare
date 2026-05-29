<?php

namespace App\Services\Sms;

use App\Contracts\SmsGateway;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TwilioSmsGateway implements SmsGateway
{
    private string $accountSid;
    private string $authToken;
    private string $from;

    public function __construct()
    {
        $this->accountSid = config('sms.twilio.account_sid', '');
        $this->authToken  = config('sms.twilio.auth_token', '');
        $this->from       = config('sms.twilio.from', '');
    }

    public function send(string $to, string $message): SmsResult
    {
        try {
            $response = Http::withBasicAuth($this->accountSid, $this->authToken)
                ->asForm()
                ->post("https://api.twilio.com/2010-04-01/Accounts/{$this->accountSid}/Messages.json", [
                    'To'   => $to,
                    'From' => $this->from,
                    'Body' => $message,
                ]);

            if ($response->successful()) {
                $data = $response->json();
                return SmsResult::ok($data['sid'] ?? '');
            }

            $error = $response->json('message') ?? $response->body();
            Log::warning('Twilio SMS failed', ['to' => $to, 'error' => $error]);
            return SmsResult::fail($error);
        } catch (\Throwable $e) {
            Log::error('Twilio SMS exception', ['to' => $to, 'error' => $e->getMessage()]);
            return SmsResult::fail($e->getMessage());
        }
    }
}
