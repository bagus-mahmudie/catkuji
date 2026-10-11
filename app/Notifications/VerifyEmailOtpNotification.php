<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class VerifyEmailOtpNotification extends Notification
{
    use Queueable;

    public function __construct(
        public string $code,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Kode Verifikasi Email')
            ->greeting('Halo!')
            ->line('Gunakan kode berikut untuk memverifikasi email akun kamu:')
            ->line("Kode verifikasi: {$this->code}")
            ->line('Kode ini berlaku selama 10 menit.')
            ->line('Jika kamu tidak merasa melakukan pendaftaran, abaikan email ini.');
    }
}