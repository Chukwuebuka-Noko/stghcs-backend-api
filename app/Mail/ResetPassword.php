<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ResetPassword extends Mailable
{
    use Queueable, SerializesModels;

    public $user,$token, $url,$expire;

    /**
     * Create a new message instance.
     */
    public function __construct(User $user,$token, $url,$expire)
    {
        $this->user = $user;
        $this->token = $token;
        $this->url = $url;
        $this->expire = $expire;
    }

    public function build()
    {
        return $this->subject('Your Password Reset Link')
        ->view('mail-template.reset-password')
        ->from('no-reply@stghcs.com', 'IT-STGHCS')
        ->with([
            'user' => $this->user,
            'url' => $this->url,
            'expire' => $this->expire
        ]);
    }

    /**
     * Get the message envelope.
     */
    // public function envelope(): Envelope
    // {
    //     return new Envelope(
    //         subject: 'Reset Password',
    //     );
    // }

    /**
     * Get the message content definition.
     */
    // public function content(): Content
    // {
    //     return new Content(
    //         view: 'view.name',
    //     );
    // }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    // public function attachments(): array
    // {
    //     return [];
    // }
}
