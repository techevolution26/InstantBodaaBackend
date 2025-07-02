<?php
namespace App\Notifications;

use App\Models\ServiceRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class JobRequested extends Notification
{
    use Queueable;

    protected ServiceRequest $ride;

    public function __construct(ServiceRequest $ride)
    {
        $this->ride = $ride;
    }

    public function via($notifiable)
    {
        // you can add 'database', 'broadcast', 'fcm', etc.
        return ['mail', 'database'];
    }

    public function toMail($notifiable)
    {
        return (new MailMessage)
                    ->subject('New Ride Request')
                    ->line("A new ride has just been requested by {$this->ride->user->name}.")
                    ->action('View Ride', url("/requests/{$this->ride->id}"))
                    ->line('Thank you for using our application!');
    }

    public function toDatabase($notifiable)
    {
        return [
            'ride_id'    => $this->ride->id,
            'user_id'    => $this->ride->user_id,
            'pickup_lat' => $this->ride->pickup_lat,
            'pickup_lng' => $this->ride->pickup_lng,
        ];
    }
}
