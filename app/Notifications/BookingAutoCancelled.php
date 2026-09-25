<?php

namespace App\Notifications;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class BookingAutoCancelled extends Notification
{
    use Queueable;

    public function __construct(public Booking $booking)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'booking_id' => $this->booking->id,
            'desk_code' => $this->booking->desk->code,
            'booking_date' => $this->booking->booking_date->format('d/m/Y'),
            'slot' => $this->booking->timeSlot->name,
        ];
    }
}