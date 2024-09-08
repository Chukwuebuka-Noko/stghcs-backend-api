<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Bus\Queueable;

class CustomResetPasswordNotification extends Notification implements ShouldQueue
{
    use Queueable;
    public $token;

    public function __construct($token)
    {
        $this->token = $token;
    }

    public function via($notifiable)
    {
        return ['mail'];
    }

    public function toMail($notifiable)
    {
        $url = url(route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->email  // Including the email in the URL
        ], false));
        $expire = config('auth.passwords.' . config('auth.defaults.passwords') . '.expire');

        return (new MailMessage)
            ->view(
                'mail-template.reset-password',
                [
                    'url' => $url,
                    'expire' => $expire,
                    'user' => $notifiable // Passing the user object to the view
                ]
            )
            ->from('no-reply@stghcs.com')
            ->subject('Your Password Reset Link');
    }
}
