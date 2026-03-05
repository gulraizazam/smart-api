<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class CashflowDailyDigest extends Mailable
{
    use Queueable, SerializesModels;

    public array $digestData;

    public function __construct(array $digestData)
    {
        $this->digestData = $digestData;
    }

    public function build()
    {
        return $this->subject('Cash Flow Daily Digest — ' . now()->format('d M Y'))
            ->view('emails.cashflow.daily-digest')
            ->with(['data' => $this->digestData]);
    }
}
