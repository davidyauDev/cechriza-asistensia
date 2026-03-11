<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Mail\TardanzaNotificadaMail;
use Carbon\Carbon;


class TardanzaController extends Controller
{
    public function enviarCorreoTardanza(Request $request)
    {
        $data = $request->validate([
            'email'           => 'required|email',
            'nombre'          => 'required|string',
            'minutos_tardanza' => 'nullable|min:1',
            'scheduled_time'   => 'nullable|min:1',
            'start_date'       => 'nullable|date|required_with:end_date',
            'end_date'         => 'nullable|date|after_or_equal:start_date|required_with:start_date',
            'nro'              => 'nullable|integer|min:1',
            'dni'              => 'nullable|string|max:20',
            'apellidos'        => 'nullable|string|max:120',
            'departamento'     => 'nullable|string|max:120',
            'empresa'          => 'nullable|string|max:120',
        ]);

        $minutosTardanza = $data['minutos_tardanza'] ?? $data['scheduled_time'] ?? null;
        if ($minutosTardanza === null) {
            return response()->json([
                'message' => 'El campo minutos_tardanza (o scheduled_time) es requerido',
            ], 422);
        }

        $startDate = isset($data['start_date']) ? Carbon::parse($data['start_date']) : Carbon::now();
        $endDate = isset($data['end_date']) ? Carbon::parse($data['end_date']) : Carbon::now();

        $fechaInicio = $startDate->format('Y-m-d');
        $fechaFin = $endDate->format('Y-m-d');

        $empresa = trim((string) ($data['empresa'] ?? ''));
        $empresaNormalizada = strtolower($empresa);
        $empresaNormalizada = preg_replace('/\s+/', ' ', $empresaNormalizada);

        $mailer = 'smtp';
        $fromAddress = config('mail.from.address');
        $fromName = config('mail.from.name');

        $contieneCechriza = !empty($empresaNormalizada) && str_contains($empresaNormalizada, 'cechriza');
        $contieneYdieza = !empty($empresaNormalizada) && str_contains($empresaNormalizada, 'ydieza');

        $empresaEsCechriza = $empresaNormalizada === 'cechriza sac';
        $empresaEsYdieza = $empresaNormalizada === 'ydieza sac';

        if ($empresaEsYdieza || ($contieneYdieza && !$contieneCechriza)) {
            $mailer = 'smtp_alt';
            $fromAddress = config('mail.from_alt.address')
                ?: config('mail.mailers.smtp_alt.username')
                ?: $fromAddress;
            $fromName = config('mail.from_alt.name') ?: $fromName;
        }

        if ($contieneCechriza && $contieneYdieza && !($empresaEsCechriza || $empresaEsYdieza)) {
            Log::warning('tardanza.mailer_empresa_ambigua', [
                'empresa_raw' => $empresa,
                'empresa_norm' => $empresaNormalizada,
            ]);
        }

        Log::info('tardanza.mailer_seleccionado', [
            'empresa_raw' => $empresa ?: null,
            'empresa_norm' => $empresaNormalizada ?: null,
            'mailer' => $mailer,
        ]);

        $mailable = new TardanzaNotificadaMail(
            $data['nombre'],
            $minutosTardanza,
            null,
            null,
            null,
            $fechaInicio,
            $fechaFin,
            $data['nro'] ?? null,
            $data['dni'] ?? null,
            $data['apellidos'] ?? null,
            $data['departamento'] ?? null,
            $data['empresa'] ?? null
        );

        if (!empty($fromAddress)) {
            $mailable->from($fromAddress, $fromName);
        }

        $ccRecipients = $mailer === 'smtp_alt'
            ? 'flavia.veliz@cechriza.com'
            : 'emma.julian@cechriza.com';

        $bccRecipients = $mailer === 'smtp_alt'
            ? []
            : ['flavia.veliz@cechriza.com'];

        $pendingMail = Mail::mailer($mailer)
            ->to($data['email'])
            ->cc($ccRecipients);

        if (!empty($bccRecipients)) {
            $pendingMail->bcc($bccRecipients);
        }

        $pendingMail->queue($mailable);

        $response = [
            'message' => 'Correo de tardanza enviado correctamente',
            'minutos_tardanza' => $minutosTardanza,
            'start_date' => $fechaInicio,
            'end_date' => $fechaFin,
            'nro' => $data['nro'] ?? null,
            'dni' => $data['dni'] ?? null,
        ];

        if (config('app.debug')) {
            $response['mailer'] = $mailer;
            $response['empresa'] = $empresa ?: null;
        }

        return response()->json($response);
    }
}
