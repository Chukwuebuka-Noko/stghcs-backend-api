<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Queue\SerializesModels;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendOTP extends Mailable
{
    use Queueable, SerializesModels;

    public $user,$otp, $expire;

    /**
     * Create a new message instance.
     */
    public function __construct(User $user,$otp, $expire)
    {
        $this->user = $user;
        $this->otp = $otp;
        $this->expire = $expire;
    }

    public function build()
    {
        return $this->subject('Your Password Reset OTP')
        ->view('mail-template.reset-otp-password')
        ->from('no-reply@stghcs.com', 'IT-STGHCS')
        ->with([
            'user' => $this->user,
            'otp' => $this->otp,
            'expire' => $this->expire
        ]);
    }

    // /**
    //  * Get the message envelope.
    //  */
    // public function envelope(): Envelope
    // {
    //     return new Envelope(
    //         subject: 'Send O T P',
    //     );
    // }

    // /**
    //  * Get the message content definition.
    //  */
    // public function content(): Content
    // {
    //     return new Content(
    //         view: 'view.name',
    //     );
    // }

    // /**
    //  * Get the attachments for the message.
    //  *
    //  * @return array<int, \Illuminate\Mail\Mailables\Attachment>
    //  */
    // public function attachments(): array
    // {
    //     return [];
    // }
}
