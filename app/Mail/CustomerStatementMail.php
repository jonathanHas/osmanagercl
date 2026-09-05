<?php

namespace App\Mail;

use App\Services\CustomerStatementService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CustomerStatementMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * @param  array<string, mixed>  $ctx  Context from CustomerStatementService::build()
     */
    public function __construct(public array $ctx) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Statement of account — '.$this->ctx['to']->format('j M Y'),
        );
    }

    public function content(): Content
    {
        // `with:` is required — a Mailable only exposes its public properties to
        // the view, which here would be $ctx alone. The blade reads $customer,
        // $aging, $open_invoices etc. directly, so unpack the context.
        return new Content(
            view: 'emails.customer-statement',
            with: $this->ctx,
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        $filename = app(CustomerStatementService::class)
            ->filename($this->ctx['customer'], $this->ctx['to']);

        return [
            Attachment::fromData(
                fn () => Pdf::loadView('customer-statements._pdf', $this->ctx)->setPaper('a4')->output(),
                $filename
            )->withMime('application/pdf'),
        ];
    }
}
