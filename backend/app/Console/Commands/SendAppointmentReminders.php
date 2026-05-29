<?php

namespace App\Console\Commands;

use App\Jobs\SmsNotificationJob;
use App\Models\Appointment;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SendAppointmentReminders extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'appointments:send-reminders';

    /**
     * The console command description.
     */
    protected $description = 'Send SMS reminders for appointments due within the next 24 hours.';

    public function handle(): int
    {
        $now      = now();
        $cutoff   = now()->addHours(24);

        $appointments = Appointment::with('patient')
            ->whereBetween('scheduled_at', [$now, $cutoff])
            ->where('sms_reminder_sent', false)
            ->where('status', 'scheduled')
            ->get();

        if ($appointments->isEmpty()) {
            $this->info('No pending appointment reminders.');
            return self::SUCCESS;
        }

        $sent = 0;

        foreach ($appointments as $appointment) {
            DB::transaction(function () use ($appointment, &$sent) {
                // Re-fetch with a lock to prevent duplicate sends in concurrent runs
                $locked = Appointment::where('id', $appointment->id)
                    ->where('sms_reminder_sent', false)
                    ->lockForUpdate()
                    ->first();

                if (! $locked) {
                    // Already processed by another worker
                    return;
                }

                $patient = $locked->patient;

                if (! $patient || ! $patient->phone) {
                    Log::warning('SendAppointmentReminders: patient has no phone', [
                        'appointment_id' => $locked->id,
                        'patient_id'     => $locked->patient_id,
                    ]);
                    return;
                }

                $scheduledAt = $locked->scheduled_at->format('D, d M Y \a\t H:i');
                $message     = "Reminder: You have an appointment scheduled for {$scheduledAt}. "
                    . "Please arrive 15 minutes early. Reply STOP to opt out.";

                SmsNotificationJob::dispatch(
                    to: $patient->phone,
                    message: $message,
                    recipientId: (string) $patient->id,
                );

                $locked->update(['sms_reminder_sent' => true]);
                $sent++;
            });
        }

        $this->info("Dispatched {$sent} appointment reminder(s).");
        return self::SUCCESS;
    }
}
