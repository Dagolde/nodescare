<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PatientAdmitted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly string $patientRecordId,
        public readonly string $patientName,
        public readonly string $admittedBy,
    ) {}

    public function broadcastOn(): Channel
    {
        return new PrivateChannel('hospital.admin');
    }

    public function broadcastAs(): string
    {
        return 'patient.admitted';
    }

    public function broadcastWith(): array
    {
        return [
            'patient_record_id' => $this->patientRecordId,
            'patient_name'      => $this->patientName,
            'admitted_by'       => $this->admittedBy,
        ];
    }
}
