<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TardanzaNotificadaMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Variables para la vista de correo
     */
    public $nombre;
    public $minutosTardanza;
    public $horaIngreso;
    public $horaProgramada;
    public $fecha;

    /**
     * Create a new message instance.
     */
    public function __construct($nombre, $minutosTardanza, $horaIngreso, $horaProgramada, $fecha)
    {
        $this->nombre = $nombre;
        $this->minutosTardanza = $minutosTardanza;
        $this->horaIngreso = $horaIngreso;
        $this->horaProgramada = $horaProgramada;
        $this->fecha = $fecha;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Tardanza Notificada Mail',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'emails.tardanza_notificada',
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
