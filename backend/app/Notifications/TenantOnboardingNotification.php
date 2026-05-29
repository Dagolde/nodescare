<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TenantOnboardingNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public string $hospitalName,
        public string $adminName,
        public string $adminEmail,
        public string $temporaryPassword,
        public string $subdomain
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
        $loginUrl = "https://{$this->subdomain}.hhms.app/login";

        return (new MailMessage)
            ->subject('Welcome to HHMS - Your Hospital Account is Ready')
            ->greeting("Hello {$this->adminName},")
            ->line("Welcome to the Hospital Health Management System!")
            ->line("Your hospital, **{$this->hospitalName}**, has been successfully provisioned and is ready to use.")
            ->line('')
            ->line('**Your Login Credentials:**')
            ->line("Email: {$this->adminEmail}")
            ->line("Temporary Password: `{$this->temporaryPassword}`")
            ->line('')
            ->line('**Important Security Notice:**')
            ->line('Please change your password immediately after your first login.')
            ->line('')
            ->action('Login to Your Dashboard', $loginUrl)
            ->line('')
            ->line('**Getting Started:**')
            ->line('1. Log in using the credentials above')
            ->line('2. Change your password in Account Settings')
            ->line('3. Set up your hospital departments')
            ->line('4. Create staff accounts for your team')
            ->line('5. Configure your patient workflow')
            ->line('')
            ->line('If you have any questions or need assistance, please contact our support team.')
            ->line('Thank you for choosing HHMS!');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'hospital_name' => $this->hospitalName,
            'admin_name' => $this->adminName,
            'admin_email' => $this->adminEmail,
            'subdomain' => $this->subdomain,
        ];
    }
}
