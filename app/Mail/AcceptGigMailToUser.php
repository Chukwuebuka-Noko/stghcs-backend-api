<?php

namespace App\Mail;

use App\Models\AssignGig;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Queue\SerializesModels;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Contracts\Queue\ShouldQueue;

class AcceptGigMailToUser extends Mailable
{
    use Queueable, SerializesModels;

    public $user,$client, $gig;

    /**
     * Create a new message instance.
     */
    public function __construct($user, $client,AssignGig $gig)
    {
        $this->user = $user;
        $this->client = $client;
        $this->gig = $gig;
    }

    public function build()
    {
        return $this->subject('Gig Acceptance')
        ->view('mail-template.user-gig-accept')
        ->from('no-reply@stghcs.com', 'IT-STGHCS')
        ->with([
            'user' => $this->user,
            'client' => $this->client,
            'gig' => $this->gig
        ]);
    }
}
