<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Carbon\Carbon;

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
    public $fechaInicio;
    public $fechaFin;
    public $periodoTexto;
    public $tardanzaTexto;
    public $tardanzaMinutos;
    public $tardanzaHHMM;
    public $fechaCorteTexto;

    public $nro;
    public $dni;
    public $apellidos;
    public $departamento;
    public $empresa;

    /**
     * Create a new message instance.
     */
    public function __construct(
        $nombre,
        $minutosTardanza,
        $horaIngreso,
        $horaProgramada,
        $fecha,
        $fechaInicio = null,
        $fechaFin = null,
        $nro = null,
        $dni = null,
        $apellidos = null,
        $departamento = null,
        $empresa = null
    )
    {
        $this->nombre = $nombre;
        $this->minutosTardanza = $minutosTardanza;
        $this->horaIngreso = $horaIngreso;
        $this->horaProgramada = $horaProgramada;
        $this->fecha = $fecha;
        $this->fechaInicio = $fechaInicio;
        $this->fechaFin = $fechaFin;
        $this->periodoTexto = $this->formatearPeriodo($fechaInicio, $fechaFin) ?: ($fecha ?: null);
        $this->tardanzaTexto = $this->formatearTardanza($minutosTardanza);

        $this->tardanzaMinutos = $this->parsearMinutos($minutosTardanza);
        $this->tardanzaHHMM = $this->tardanzaMinutos !== null ? $this->formatearHHMM($this->tardanzaMinutos) : null;
        $this->fechaCorteTexto = $this->formatearFechaCorte($fechaFin ?: $fecha);

        $this->nro = $nro;
        $this->dni = $dni;
        $this->apellidos = $apellidos;
        $this->departamento = $departamento;
        $this->empresa = $empresa;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $valorTardanza = is_numeric($this->minutosTardanza)
            ? ((int) $this->minutosTardanza) . ' min'
            : (string) $this->minutosTardanza;

        $periodo = (!empty($this->fechaInicio) && !empty($this->fechaFin))
            ? "{$this->fechaInicio} a {$this->fechaFin}"
            : null;

        return new Envelope(
            subject: $periodo
                ? "Tardanza acumulada ({$periodo}) — {$valorTardanza}"
                : "Tardanza acumulada — {$valorTardanza}",
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.tardanza_notificada',
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

    private function formatearTardanza($valor): string
    {
        if (is_numeric($valor)) {
            return $this->formatearMinutos((int) $valor);
        }

        $texto = trim((string) $valor);
        if ($texto === '') {
            return '—';
        }

        if (preg_match('/^\d{1,2}:\d{2}$/', $texto)) {
            [$h, $m] = array_map('intval', explode(':', $texto, 2));
            return $this->formatearMinutos(($h * 60) + $m);
        }

        return $texto;
    }

    private function parsearMinutos($valor): ?int
    {
        if (is_numeric($valor)) {
            return max(0, (int) $valor);
        }

        $texto = trim((string) $valor);
        if ($texto === '') {
            return null;
        }

        if (preg_match('/^\d{1,2}:\d{2}$/', $texto)) {
            [$h, $m] = array_map('intval', explode(':', $texto, 2));
            return max(0, ($h * 60) + $m);
        }

        return null;
    }

    private function formatearMinutos(int $minutos): string
    {
        if ($minutos <= 0) {
            return '0 minutos';
        }

        $horas = intdiv($minutos, 60);
        $mins = $minutos % 60;

        $partes = [];
        if ($horas > 0) {
            $partes[] = $horas . ' ' . ($horas === 1 ? 'hora' : 'horas');
        }
        if ($mins > 0) {
            $partes[] = $mins . ' ' . ($mins === 1 ? 'minuto' : 'minutos');
        }

        return implode(' ', $partes);
    }

    private function formatearHHMM(int $minutos): string
    {
        $horas = intdiv(max(0, $minutos), 60);
        $mins = max(0, $minutos) % 60;

        return str_pad((string) $horas, 2, '0', STR_PAD_LEFT) . ':' . str_pad((string) $mins, 2, '0', STR_PAD_LEFT);
    }

    private function formatearPeriodo($inicio, $fin): ?string
    {
        if (empty($inicio) || empty($fin)) {
            return null;
        }

        try {
            $start = Carbon::parse($inicio)->locale('es');
            $end = Carbon::parse($fin)->locale('es');
        } catch (\Throwable $e) {
            return "{$inicio} al {$fin}";
        }

        if ($start->year === $end->year) {
            if ($start->month === $end->month) {
                $mes = $end->translatedFormat('F');
                return "{$start->day} al {$end->day} de {$mes} de {$end->year}";
            }

            $inicioTxt = $start->translatedFormat('j \\d\\e F');
            $finTxt = $end->translatedFormat('j \\d\\e F \\d\\e Y');
            return "{$inicioTxt} al {$finTxt}";
        }

        $inicioTxt = $start->translatedFormat('j \\d\\e F \\d\\e Y');
        $finTxt = $end->translatedFormat('j \\d\\e F \\d\\e Y');
        return "{$inicioTxt} al {$finTxt}";
    }

    private function formatearFechaCorte($fecha): ?string
    {
        if (empty($fecha)) {
            return null;
        }

        try {
            return Carbon::parse($fecha)->locale('es')->translatedFormat('j \\d\\e F \\d\\e Y');
        } catch (\Throwable $e) {
            return (string) $fecha;
        }
    }
}
