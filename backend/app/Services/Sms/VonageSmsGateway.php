<?php

namespace App\Services\Sms;

use App\Contracts\SmsGateway;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class VonageSmsGateway implements SmsGateway
{
    private string $apiKey;
    private string $apiSecret;
    private string $from;

    public function __construct()
    {
        $this->apiKey    = config('sms.vonage.api_key', '');
        $this->apiSecret = config('sms.vonage.api_secret', '');
        $this->from      = config('sms.vonage.from', 'HHMS');
    }

    public function send(string $to, string $message): SmsResult
    {
        try {
            $response = Http::post('https://rest.nexmo.com/sms/json', [
                'api_key'    => $this->apiKey,
                'api_secret' => $this->apiSecret,
                'to'         => $to,
                'from'       => $this->from,
                'text'       => $message,
            ]);

            if ($response->successful()) {
                $data     = $response->json();
                $messages = $data['messages'][0] ?? [];
                $status   = $messages['status'] ?? '1';

                if ($status === '0') {
                    return SmsResult::ok($messages['message-id'] ?? '');
                }

                $error = $messages['error-text'] ?? 'Unknown Vonage error';
                Log::warning('Vonage SMS failed', ['to' => $to, 'error' => $error]);
                return SmsResult::fail($error);
            }

            $error = $response->body();
            Log::warning('Vonage SMS HTTP error', ['to' => $to, 'error' => $error]);
            return SmsResult::fail($error);
        } catch (\Throwable $e) {
            Log::error('Vonage SMS exception', ['to' => $to, 'error' => $e->getMessage()]);
            return SmsResult::fail($e->getMessage());
        }
    }
}
