<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SolicitudGasto\ComprobanteGasto;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class ComprobanteGastoRegistroController extends Controller
{
    use ApiResponseTrait;

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'solicitud_gasto_id' => ['required', 'integer', 'min:1', 'exists:mysql_external.solicitudes_gasto,id'],
            'tipo' => ['required', 'string', 'max:50'],
            'numero' => ['required', 'string', 'max:100'],
            'monto' => ['required', 'numeric', 'min:0'],
            'archivo' => ['required', 'file', 'max:10240', 'mimes:jpg,jpeg,png,pdf,webp'],
        ]);

        $storedPath = null;

        try {
            /** @var UploadedFile $archivo */
            $archivo = $request->file('archivo');
            $storedPath = $this->storeComprobanteFile((int) $validated['solicitud_gasto_id'], $archivo);
            $publicUrl = $this->buildPublicUrl($storedPath);

            $payload = DB::connection('mysql_external')->transaction(function () use ($validated, $publicUrl): array {
                $comprobante = new ComprobanteGasto();
                $comprobante->setConnection('mysql_external');
                $comprobante->fill([
                    'solicitud_gasto_id' => (int) $validated['solicitud_gasto_id'],
                    'tipo' => $validated['tipo'],
                    'numero' => $validated['numero'],
                    'monto' => (float) $validated['monto'],
                    'archivo_url' => $publicUrl,
                ]);
                $comprobante->save();

                return [
                    'id' => (int) $comprobante->id,
                    'solicitud_gasto_id' => (int) $comprobante->solicitud_gasto_id,
                    'tipo' => $comprobante->tipo,
                    'numero' => $comprobante->numero,
                    'monto' => (float) $comprobante->monto,
                    'archivo_url' => $comprobante->archivo_url,
                ];
            });

            $this->sendComprobanteRegistradoNotification($payload);

            return $this->successResponse($payload, 'Comprobante de gasto registrado correctamente', 201);
        } catch (Throwable $e) {
            if ($storedPath !== null && Storage::disk('public')->exists($storedPath)) {
                Storage::disk('public')->delete($storedPath);
            }

            report($e);

            if (config('app.debug')) {
                return $this->errorResponse('No se pudo registrar el comprobante de gasto: '.$e->getMessage(), 500);
            }

            return $this->errorResponse('No se pudo registrar el comprobante de gasto.', 500);
        }
    }

    protected function storeComprobanteFile(int $solicitudGastoId, UploadedFile $file): string
    {
        $directory = 'uploads/solicitudes-gasto/'.$solicitudGastoId.'/comprobantes';
        $extension = strtolower((string) ($file->getClientOriginalExtension() ?: $file->extension() ?: 'bin'));
        $filename = sprintf(
            'comprobante_%d_%s_%s.%s',
            $solicitudGastoId,
            now()->format('YmdHis'),
            Str::lower(Str::random(8)),
            $extension
        );

        return $file->storeAs($directory, $filename, 'public');
    }

    protected function buildPublicUrl(string $path): ?string
    {
        $appUrl = trim((string) config('app.url'), '/');

        if ($appUrl === '') {
            return null;
        }

        return $appUrl.'/storage/'.ltrim($path, '/');
    }

    protected function sendComprobanteRegistradoNotification(array $payload): void
    {
        $to = $this->resolveConfiguredEmails((string) config('services.solicitudes.comprobante_gasto_notify_to', ''));
        if ($to === []) {
            return;
        }

        $solicitud = $this->loadSolicitudGastoInfo((int) $payload['solicitud_gasto_id']);
        $solicitanteEmail = $this->normalizeEmail($solicitud->solicitante_email ?? null);

        $ccConfigured = $this->resolveConfiguredEmailsByArea((int) ($solicitud->id_area ?? 0));
        $cc = collect($ccConfigured);
        $correoGerencia = $this->normalizeEmail((string) config('services.solicitudes.correo_gerencia', ''));
        if ($correoGerencia !== null) {
            $cc->push($correoGerencia);
        }
        if ($solicitanteEmail !== null) {
            $cc->push($solicitanteEmail);
        }

        $toCollection = collect($to)->map(fn (string $email): string => mb_strtolower(trim($email)));
        $cc = $cc
            ->map(fn (string $email): string => mb_strtolower(trim($email)))
            ->filter(fn (string $email): bool => $email !== '' && ! $toCollection->contains($email))
            ->unique()
            ->values()
            ->all();

        $subject = sprintf('Comprobante de gasto registrado #%s', (string) ($payload['id'] ?? 'N/A'));
        $htmlBody = $this->buildComprobanteNotificationHtml($payload, $solicitud, $solicitanteEmail);
        $textBody = $this->buildComprobanteNotificationText($payload, $solicitud, $solicitanteEmail);

        try {
            Mail::mailer('smtp_test')->send([], [], function ($message) use ($to, $cc, $subject, $htmlBody, $textBody): void {
                $message->to($to)
                    ->subject($subject)
                    ->from(
                        (string) config('mail.from_test.address', config('mail.from.address')),
                        (string) config('mail.from_test.name', config('mail.from.name'))
                    );

                if ($cc !== []) {
                    $message->cc($cc);
                }

                $message->html($htmlBody);
                $message->text($textBody);
            });
        } catch (Throwable $e) {
            report($e);
        }
    }

    protected function loadSolicitudGastoInfo(int $solicitudGastoId): object
    {
        $row = DB::connection('mysql_external')
            ->table('solicitudes_gasto as sg')
            ->leftJoin('ost_staff as os', 'os.staff_id', '=', 'sg.staff_id')
            ->where('sg.id', $solicitudGastoId)
            ->select([
                'sg.id',
                'sg.staff_id',
                'sg.id_area',
                'os.email as solicitante_email',
                'os.firstname',
                'os.lastname',
            ])
            ->first();

        if ($row === null) {
            return (object) [
                'id' => $solicitudGastoId,
                'staff_id' => null,
                'id_area' => null,
                'solicitante_email' => null,
                'solicitante_nombre' => null,
            ];
        }

        $firstname = trim((string) ($row->firstname ?? ''));
        $lastname = trim((string) ($row->lastname ?? ''));
        $fullName = trim($firstname.' '.$lastname);
        $row->solicitante_nombre = $fullName !== '' ? $fullName : null;

        return $row;
    }

    /**
     * @return array<int, string>
     */
    protected function resolveConfiguredEmails(string $csv): array
    {
        return collect(explode(',', $csv))
            ->map(fn ($value): ?string => $this->normalizeEmail($value))
            ->filter(fn (?string $email): bool => $email !== null)
            ->values()
            ->all();
    }

    protected function normalizeEmail(mixed $value): ?string
    {
        $email = mb_strtolower(trim((string) ($value ?? '')));
        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return null;
        }

        return $email;
    }

    /**
     * @return array<int, string>
     */
    protected function resolveConfiguredEmailsByArea(int $idArea): array
    {
        $csv = match ($idArea) {
            1 => (string) config('services.solicitudes.comprobante_gasto_notify_cc_operaciones', ''),
            11 => (string) config('services.solicitudes.comprobante_gasto_notify_cc_ssoma', ''),
            default => '',
        };

        return $this->resolveConfiguredEmails($csv);
    }

    protected function buildComprobanteNotificationHtml(array $payload, object $solicitud, ?string $solicitanteEmail): string
    {
        $escape = static fn (?string $value): string => htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');

        $comprobanteId = (string) ($payload['id'] ?? 'N/A');
        $solicitudId = (string) ($payload['solicitud_gasto_id'] ?? 'N/A');
        $tipo = (string) ($payload['tipo'] ?? 'N/A');
        $numero = (string) ($payload['numero'] ?? 'N/A');
        $monto = isset($payload['monto']) ? number_format((float) $payload['monto'], 2, '.', '') : '0.00';
        $archivoUrl = trim((string) ($payload['archivo_url'] ?? ''));
        $solicitanteNombre = (string) ($solicitud->solicitante_nombre ?? 'N/A');
        $titulo = 'Registro de comprobante de gasto - '.$solicitanteNombre;

        $archivoLink = $archivoUrl !== ''
            ? '<a href="'.$escape($archivoUrl).'" style="color:#2563eb;text-decoration:none;">'.$escape($archivoUrl).'</a>'
            : 'N/A';

        return ''
            .'<!doctype html><html><body style="margin:0;padding:0;background:#f3f4f6;font-family:Segoe UI,Arial,sans-serif;">'
            .'<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f3f4f6;padding:24px 12px;">'
            .'<tr><td align="center">'
            .'<table role="presentation" width="640" cellpadding="0" cellspacing="0" style="max-width:640px;width:100%;background:#ffffff;border-radius:12px;overflow:hidden;border:1px solid #e5e7eb;">'
            .'<tr><td style="padding:20px 24px;background:#0f172a;color:#ffffff;">'
            .'<div style="font-size:20px;font-weight:700;">'.$escape($titulo).'</div>'
            .'<div style="font-size:13px;color:#cbd5e1;margin-top:4px;">Sistemas Cechriza</div>'
            .'</td></tr>'
            .'<tr><td style="padding:24px;">'
            .'<p style="margin:0 0 16px 0;color:#374151;font-size:14px;line-height:1.5;">Se registró un nuevo comprobante de gasto en el sistema.</p>'
            .'<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;font-size:14px;">'
            .'<tr><td style="padding:8px 0;color:#6b7280;width:180px;">Comprobante ID</td><td style="padding:8px 0;color:#111827;">'.$escape($comprobanteId).'</td></tr>'
            .'<tr><td style="padding:8px 0;color:#6b7280;">Solicitud ID</td><td style="padding:8px 0;color:#111827;">'.$escape($solicitudId).'</td></tr>'
            .'<tr><td style="padding:8px 0;color:#6b7280;">Tipo</td><td style="padding:8px 0;color:#111827;">'.$escape($tipo).'</td></tr>'
            .'<tr><td style="padding:8px 0;color:#6b7280;">Número</td><td style="padding:8px 0;color:#111827;">'.$escape($numero).'</td></tr>'
            .'<tr><td style="padding:8px 0;color:#6b7280;">Monto</td><td style="padding:8px 0;color:#111827;">S/ '.$escape($monto).'</td></tr>'
            .'<tr><td style="padding:8px 0;color:#6b7280;">Solicitante</td><td style="padding:8px 0;color:#111827;">'.$escape($solicitanteNombre).'</td></tr>'
            .'<tr><td style="padding:8px 0;color:#6b7280;">Correo solicitante</td><td style="padding:8px 0;color:#111827;">'.$escape($solicitanteEmail ?? 'N/A').'</td></tr>'
            .'<tr><td style="padding:8px 0;color:#6b7280;">Archivo</td><td style="padding:8px 0;color:#111827;">'.$archivoLink.'</td></tr>'
            .'</table>'
            .'</td></tr>'
            .'</table>'
            .'</td></tr></table>'
            .'</body></html>';
    }

    protected function buildComprobanteNotificationText(array $payload, object $solicitud, ?string $solicitanteEmail): string
    {
        return implode("\n", [
            'Se registró un comprobante de gasto.',
            'Comprobante ID: '.($payload['id'] ?? 'N/A'),
            'Solicitud ID: '.($payload['solicitud_gasto_id'] ?? 'N/A'),
            'Tipo: '.($payload['tipo'] ?? 'N/A'),
            'Numero: '.($payload['numero'] ?? 'N/A'),
            'Monto: '.(isset($payload['monto']) ? number_format((float) $payload['monto'], 2, '.', '') : '0.00'),
            'URL archivo: '.($payload['archivo_url'] ?? 'N/A'),
            'Solicitante: '.($solicitud->solicitante_nombre ?? 'N/A'),
            'Correo solicitante: '.($solicitanteEmail ?? 'N/A'),
        ]);
    }
}
