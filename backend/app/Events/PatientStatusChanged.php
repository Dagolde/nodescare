<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PatientStatusChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly string $patientRecordId,
        public readonly string $newStatus,
        public readonly string $departmentId,
    ) {}

    public function broadcastOn(): Channel
    {
        return new PrivateChannel("department.{$this->departmentId}");
    }

    public function broadcastAs(): string
    {
        return 'patient.status_changed';
    }

    public function broadcastWith(): array
    {
        return [
            'patient_record_id' => $this->patientRecordId,
            'new_status'        => $this->newStatus,
        ];
    }
}
