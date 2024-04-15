<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Queue\SerializesModels;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Contracts\Queue\ShouldQueue;

class WelcomeMail extends Mailable
{
    use Queueable, SerializesModels;

    public $user,$password, $url;

    /**
     * Create a new message instance.
     */
    public function __construct(User $user,$password, $url)
    {
        $this->user = $user;
        $this->password = $password;
        $this->url = $url;
    }

    public function build()
    {
        return $this->subject('Welcome To STGHCS')
        ->view('mail-template.temporary-password')
        ->from('no-reply@stghcs.com', 'IT-STGHCS')
        ->with([
            'user' => $this->user,
            'url' => $this->url,
            'password' => $this->password
        ]);
    }
}
