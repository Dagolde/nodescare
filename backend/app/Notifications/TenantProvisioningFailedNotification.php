<?php

namespace App\Notifications;

use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TenantProvisioningFailedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public Tenant $tenant,
        public string $adminEmail,
        public string $adminName,
        public string $errorMessage
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
            ->error()
            ->subject('URGENT: Tenant Provisioning Failed - ' . $this->tenant->name)
            ->line('A tenant provisioning job has failed and requires immediate attention.')
            ->line('')
            ->line('**Tenant Details:**')
            ->line("Hospital Name: {$this->tenant->name}")
            ->line("Subdomain: {$this->tenant->subdomain}")
            ->line("Tenant ID: {$this->tenant->id}")
            ->line("Admin Name: {$this->adminName}")
            ->line("Admin Email: {$this->adminEmail}")
            ->line('')
            ->line('**Error Details:**')
            ->line($this->errorMessage)
            ->line('')
            ->line('**Action Required:**')
            ->line('1. Review the error logs for detailed information')
            ->line('2. Investigate the root cause of the failure')
            ->line('3. Manually provision the tenant or retry the provisioning job')
            ->line('4. Contact the hospital admin to inform them of the delay')
            ->line('')
            ->line('The tenant status has been marked as FAILED in the system.');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'tenant_id' => $this->tenant->id,
            'tenant_name' => $this->tenant->name,
            'subdomain' => $this->tenant->subdomain,
            'admin_name' => $this->adminName,
            'admin_email' => $this->adminEmail,
            'error_message' => $this->errorMessage,
        ];
    }
}
