<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class CashflowMonthlyReport extends Mailable
{
    use Queueable, SerializesModels;

    public array $reportData;

    public function __construct(array $reportData)
    {
        $this->reportData = $reportData;
    }

    public function build()
    {
        return $this->subject('Cash Flow Monthly Report — ' . ($this->reportData['month_label'] ?? ''))
            ->view('emails.cashflow.monthly-report')
            ->with(['data' => $this->reportData]);
    }
}
