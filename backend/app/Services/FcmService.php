<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;

class FcmService
{
    public function __construct(
        private readonly Messaging $messaging,
    ) {}

    /**
     * Send a push notification to all registered devices for a user.
     */
    public function sendToUser(string $userId, string $title, string $body, array $data = []): void
    {
        $tokens = DB::table('user_device_tokens')
            ->where('user_id', $userId)
            ->pluck('token')
            ->toArray();

        if (empty($tokens)) {
            return;
        }

        $notification = Notification::create($title, $body);

        foreach ($tokens as $token) {
            try {
                $message = CloudMessage::withTarget('token', $token)
                    ->withNotification($notification)
                    ->withData($data);

                $this->messaging->send($message);
            } catch (\Throwable $e) {
                Log::warning('FCM send failed', [
                    'user_id' => $userId,
                    'token'   => $token,
                    'error'   => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Store or update an FCM device token for a user.
     */
    public function storeDeviceToken(string $userId, string $token, string $platform): void
    {
        DB::table('user_device_tokens')->updateOrInsert(
            ['user_id' => $userId, 'token' => $token],
            [
                'tenant_id'  => app('current_tenant_id'),
                'platform'   => $platform,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }
}
