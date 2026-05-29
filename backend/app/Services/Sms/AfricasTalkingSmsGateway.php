<?php

namespace App\Services\Sms;

use App\Contracts\SmsGateway;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AfricasTalkingSmsGateway implements SmsGateway
{
    private string $apiKey;
    private string $username;
    private string $from;

    public function __construct()
    {
        $this->apiKey   = config('sms.africastalking.api_key', '');
        $this->username = config('sms.africastalking.username', 'sandbox');
        $this->from     = config('sms.africastalking.from', '');
    }

    public function send(string $to, string $message): SmsResult
    {
        try {
            $payload = [
                'username' => $this->username,
                'to'       => $to,
                'message'  => $message,
            ];

            if ($this->from) {
                $payload['from'] = $this->from;
            }

            $response = Http::withHeaders([
                'apiKey' => $this->apiKey,
                'Accept' => 'application/json',
            ])->asForm()->post('https://api.africastalking.com/version1/messaging', $payload);

            if ($response->successful()) {
                $data       = $response->json();
                $recipients = $data['SMSMessageData']['Recipients'][0] ?? [];
                $status     = $recipients['status'] ?? 'Unknown';

                if ($status === 'Success') {
                    return SmsResult::ok($recipients['messageId'] ?? '');
                }

                Log::warning('Africa\'s Talking SMS failed', ['to' => $to, 'status' => $status]);
                return SmsResult::fail($status);
            }

            $error = $response->body();
            Log::warning('Africa\'s Talking SMS HTTP error', ['to' => $to, 'error' => $error]);
            return SmsResult::fail($error);
        } catch (\Throwable $e) {
            Log::error('Africa\'s Talking SMS exception', ['to' => $to, 'error' => $e->getMessage()]);
            return SmsResult::fail($e->getMessage());
        }
    }
}
