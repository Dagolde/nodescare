<?php

namespace App\Jobs;

use App\Mail\GenericNotificationMailable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class EmailNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private readonly string $to,
        private readonly string $subject,
        private readonly string $body,
        private readonly array $data = [],
    ) {}

    public function handle(): void
    {
        Mail::to($this->to)->send(
            new GenericNotificationMailable(
                subject: $this->subject,
                body: $this->body,
                data: $this->data,
            )
        );
    }
}
