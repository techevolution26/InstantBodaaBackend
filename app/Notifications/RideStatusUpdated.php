<?php

namespace App\Notifications;

use App\Models\ServiceRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class RideStatusUpdated extends Notification
{
    use Queueable;

    protected ServiceRequest $ride;

    public function __construct(ServiceRequest $ride)
    {
        $this->ride = $ride;
    }

    public function via($notifiable)
    {
        return ['mail', 'database'];
    }

    public function toMail($notifiable)
    {
        return (new MailMessage)
                    ->subject('Ride Status Updated')
                    ->line("The status of ride #{$this->ride->id} is now “{$this->ride->status}.”")
                    ->action('View Ride', url("/requests/{$this->ride->id}"))
                    ->line('Thank you for staying with us!');
    }

    public function toDatabase($notifiable)
    {
        return [
            'ride_id' => $this->ride->id,
            'status'  => $this->ride->status,
        ];
    }
}
