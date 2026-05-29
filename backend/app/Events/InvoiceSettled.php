<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class InvoiceSettled implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly string $tenantId,
        public readonly string $patientRecordId,
        public readonly string $invoiceId,
        public readonly string $patientName,
        public readonly string $patientNumber,
    ) {}

    public function broadcastOn(): Channel
    {
        return new PrivateChannel("tenant.{$this->tenantId}");
    }

    public function broadcastAs(): string
    {
        return 'invoice.settled';
    }

    public function broadcastWith(): array
    {
        return [
            'patient_record_id' => $this->patientRecordId,
            'invoice_id'        => $this->invoiceId,
            'patient_name'      => $this->patientName,
            'patient_number'    => $this->patientNumber,
            'message'           => "Payment received for {$this->patientName}",
        ];
    }
}
