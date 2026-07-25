<?php

namespace App\Mail;

use App\Services\SupplierSalesReportService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SupplierDailySalesMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * @param  array{supplier: \App\Models\AccountingSupplier, date: Carbon, items: array, totals: array}  $report
     */
    public function __construct(public array $report) {}

    public function envelope(): Envelope
    {
        $date = $this->report['date'] instanceof Carbon
            ? $this->report['date']
            : Carbon::parse($this->report['date']);

        return new Envelope(
            subject: 'Your product sales — '.$date->format('D j M Y'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.supplier-daily-sales',
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        // The supplier can opt out of the CSV attachment.
        if (! ($this->report['attach_csv'] ?? true)) {
            return [];
        }

        $date = $this->report['date'] instanceof Carbon
            ? $this->report['date']
            : Carbon::parse($this->report['date']);

        $filename = 'sales-'.$date->format('Y-m-d').'.csv';

        return [
            Attachment::fromData(
                fn () => app(SupplierSalesReportService::class)->toCsv($this->report),
                $filename
            )->withMime('text/csv'),
        ];
    }
}
