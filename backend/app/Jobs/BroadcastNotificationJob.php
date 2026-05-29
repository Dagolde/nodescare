<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class BroadcastNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @param  class-string  $eventClass  Fully-qualified event class name
     * @param  array<string, mixed>  $payload  Constructor arguments for the event
     */
    public function __construct(
        private readonly string $eventClass,
        private readonly array $payload,
    ) {}

    public function handle(): void
    {
        if (! class_exists($this->eventClass)) {
            Log::error('BroadcastNotificationJob: event class not found', [
                'event_class' => $this->eventClass,
            ]);
            return;
        }

        $event = new ($this->eventClass)(...$this->payload);
        event($event);
    }
}
