<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AppointmentReminder implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly string $appointmentId,
        public readonly string $patientName,
        public readonly string $scheduledAt,
        public readonly string $userId,
    ) {}

    public function broadcastOn(): Channel
    {
        return new PrivateChannel("user.{$this->userId}");
    }

    public function broadcastAs(): string
    {
        return 'appointment.reminder';
    }

    public function broadcastWith(): array
    {
        return [
            'appointment_id' => $this->appointmentId,
            'patient_name'   => $this->patientName,
            'scheduled_at'   => $this->scheduledAt,
        ];
    }
}
