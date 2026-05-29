<?php

namespace App\Jobs;

use App\Contracts\SmsGateway;
use App\Models\AuditLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SmsNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Maximum number of attempts before the job is considered failed. */
    public int $tries = 3;

    public function __construct(
        private readonly string $to,
        private readonly string $message,
        private readonly ?string $recipientId = null,
    ) {}

    /**
     * Exponential backoff: 10s, 30s, 60s between retries.
     *
     * @return array<int>
     */
    public function backoff(): array
    {
        return [10, 30, 60];
    }

    public function handle(SmsGateway $smsGateway): void
    {
        $result = $smsGateway->send($this->to, $this->message);

        if (! $result->success) {
            Log::warning('SmsNotificationJob: send failed', [
                'to'    => $this->to,
                'error' => $result->error,
            ]);

            // Re-throw to trigger retry mechanism
            throw new \RuntimeException("SMS send failed: {$result->error}");
        }
    }

    /**
     * Handle final job failure after all retries are exhausted.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('SmsNotificationJob: permanently failed after retries', [
            'to'    => $this->to,
            'error' => $exception->getMessage(),
        ]);

        try {
            AuditLog::create([
                'actor_id'      => null,
                'actor_role'    => 'system',
                'action'        => 'notification.sms.failed',
                'resource_type' => 'sms_notification',
                'resource_id'   => null,
                'description'   => "SMS notification permanently failed after 3 attempts. Recipient: {$this->to}. Error: {$exception->getMessage()}",
                'ip_address'    => null,
                'metadata'      => [
                    'to'           => $this->to,
                    'recipient_id' => $this->recipientId,
                    'error'        => $exception->getMessage(),
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('SmsNotificationJob: failed to write audit log', ['error' => $e->getMessage()]);
        }
    }
}
