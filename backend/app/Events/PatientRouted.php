<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PatientRouted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly string $patientRecordId,
        public readonly string $patientName,
        public readonly string $patientNumber,
        public readonly ?string $fromDepartment,
        public readonly string $toDepartment,
        public readonly string $departmentId,
    ) {}

    public function broadcastOn(): Channel
    {
        return new PrivateChannel("department.{$this->departmentId}");
    }

    public function broadcastAs(): string
    {
        return 'patient.routed';
    }

    public function broadcastWith(): array
    {
        return [
            'patient_record_id' => $this->patientRecordId,
            'patient_name'      => $this->patientName,
            'patient_number'    => $this->patientNumber,
            'from_department'   => $this->fromDepartment,
            'to_department'     => $this->toDepartment,
        ];
    }
}
