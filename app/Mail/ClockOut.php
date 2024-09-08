<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ClockOut extends Mailable
{
    use Queueable, SerializesModels;

    public $timeSheet,$user, $assign_gig;

    /**
     * Create a new message instance.
     */
    public function __construct($timeSheet, $user, $assign_gig)
    {
        $this->user = $user;
        $this->timeSheet = $timeSheet;
        $this->assign_gig = $assign_gig;
    }

    public function build()
    {
        return $this->subject('Clock Out Confirmation')
        ->view('mail-template.clock-out')
        ->from('no-reply@stghcs.com', 'IT-STGHCS')
        ->with([
            'user' => $this->user,
            'time_sheet' => $this->timeSheet,
            'gig' => $this->assign_gig
        ]);
    }
}
