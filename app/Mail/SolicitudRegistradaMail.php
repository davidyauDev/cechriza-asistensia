<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SolicitudRegistradaMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array<string, mixed>  $solicitante
     * @param  array<int, int>  $areas
     * @param  array<int, array<string, mixed>>  $items
     * @param  array<int, array<string, mixed>>  $uploadedFiles
     */
    public function __construct(
        public string $ticket,
        public array $solicitante,
        public array $areas,
        public array $items,
        public bool $isPurchaseOrder,
        public ?string $justificacion = null,
        public array $uploadedFiles = [],
        public array $ccRecipients = [],
        public ?string $replyToEmail = null,
        public ?string $replyToName = null
    ) {
        //
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: ($this->isPurchaseOrder ? 'Nueva solicitud de compra ' : 'Nueva solicitud registrada ').$this->ticket,
            cc: collect($this->ccRecipients)
                ->filter()
                ->map(fn (string $email): Address => new Address($email))
                ->values()
                ->all(),
            replyTo: $this->replyToEmail
                ? [new Address($this->replyToEmail, $this->replyToName ?: null)]
                : [],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.solicitud_registrada',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
