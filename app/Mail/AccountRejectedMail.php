<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class AccountRejectedMail extends Mailable
{
    use Queueable, SerializesModels;

    public $accountOpeningRequest;
    public $reason;

    /**
     * Create a new message instance.
     */
    public function __construct($accountOpeningRequest, $reason)
    {
        $this->accountOpeningRequest = $accountOpeningRequest;
        $this->reason = $reason;
    }

    /**
     * Build the message.
     */
    public function build()
    {
        return $this->subject('Votre demande d’ouverture de compte a été rejetée')
                    ->view('emails.account_rejected');
    }
}
