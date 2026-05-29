<?php

namespace App\Events;

use App\Models\PatientVitals;
use App\Models\PatientRecord;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class VitalsRecorded implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public PatientVitals $vitals;
    public PatientRecord $patientRecord;
    public int $departmentId;
    public string $recordedByName;

    /**
     * Create a new event instance.
     */
    public function __construct(PatientVitals $vitals, PatientRecord $patientRecord, string $recordedByName)
    {
        $this->vitals = $vitals;
        $this->patientRecord = $patientRecord;
        $this->departmentId = $patientRecord->current_department_id;
        $this->recordedByName = $recordedByName;
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
        return 'VitalsRecorded';
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'patient_record_id' => $this->patientRecord->id,
            'patient_number' => $this->patientRecord->patient->patient_number ?? null,
            'patient_name' => $this->patientRecord->patient->full_name ?? null,
            'vitals' => [
                'id' => $this->vitals->id,
                'systolic_bp' => $this->vitals->systolic_bp,
                'diastolic_bp' => $this->vitals->diastolic_bp,
                'temperature' => $this->vitals->temperature,
                'pulse_rate' => $this->vitals->pulse_rate,
                'respiratory_rate' => $this->vitals->respiratory_rate,
                'oxygen_saturation' => $this->vitals->oxygen_saturation,
                'blood_glucose' => $this->vitals->blood_glucose,
                'weight' => $this->vitals->weight,
                'height' => $this->vitals->height,
                'bmi' => $this->vitals->bmi,
                'recorded_at' => $this->vitals->created_at->toIso8601String(),
            ],
            'recorded_by' => $this->recordedByName,
            'timestamp' => now()->toIso8601String(),
        ];
    }
}
