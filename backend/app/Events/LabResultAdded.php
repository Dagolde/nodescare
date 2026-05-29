<?php

namespace App\Events;

use App\Models\LabResult;
use App\Models\PatientRecord;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LabResultAdded implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public LabResult $labResult;
    public PatientRecord $patientRecord;
    public int $departmentId;

    /**
     * Create a new event instance.
     */
    public function __construct(LabResult $labResult, PatientRecord $patientRecord)
    {
        $this->labResult = $labResult;
        $this->patientRecord = $patientRecord;
        $this->departmentId = $patientRecord->current_department_id;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('department.' . $this->departmentId),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'LabResultAdded';
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'lab_result_id' => $this->labResult->id,
            'patient_record_id' => $this->patientRecord->id,
            'patient_number' => $this->patientRecord->patient->patient_number ?? null,
            'patient_name' => $this->patientRecord->patient->full_name ?? null,
            'test_type' => $this->labResult->test_type,
            'test_name' => $this->labResult->test_name,
            'result_status' => $this->labResult->result_status,
            'result_date' => $this->labResult->result_date?->toIso8601String(),
            'timestamp' => now()->toIso8601String(),
        ];
    }
}
