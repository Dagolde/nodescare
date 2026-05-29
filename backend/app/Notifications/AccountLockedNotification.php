<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AccountLockedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public User $lockedUser,
        public string $ipAddress
    ) {
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Account Locked: ' . $this->lockedUser->name)
            ->line('A user account has been locked due to multiple failed login attempts.')
            ->line('**User Details:**')
            ->line('Name: ' . $this->lockedUser->name)
            ->line('Email: ' . $this->lockedUser->email)
            ->line('Role: ' . $this->lockedUser->role)
            ->line('IP Address: ' . $this->ipAddress)
            ->line('Locked Until: ' . $this->lockedUser->locked_until->format('Y-m-d H:i:s'))
            ->line('The account will be automatically unlocked after 10 minutes.')
            ->line('If this was not an authorized attempt, please investigate immediately.');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'locked_user_id' => $this->lockedUser->id,
            'locked_user_name' => $this->lockedUser->name,
            'locked_user_email' => $this->lockedUser->email,
            'locked_user_role' => $this->lockedUser->role,
            'ip_address' => $this->ipAddress,
            'locked_until' => $this->lockedUser->locked_until->toIso8601String(),
        ];
    }
}
