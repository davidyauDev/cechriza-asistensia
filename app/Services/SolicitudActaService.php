<?php

namespace App\Services;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use PhpOffice\PhpWord\TemplateProcessor;
use Symfony\Component\Process\Process;

class SolicitudActaService
{
    /**
     * @return array{ok:bool,status:int,message:string,pdf_path?:string,pdf_name?:string}
     */
    public function generateActaPdf(ConnectionInterface $connection, int $solicitudId): array
    {
        $actaData = $this->getActaTemplateData($connection, $solicitudId);
        if ($actaData === null) {
            return [
                'ok' => false,
                'status' => 404,
                'message' => 'Solicitud no encontrada.',
            ];
        }

        $templatePath = storage_path('app/templates/CECH-SST.docx');
        if (! File::exists($templatePath)) {
            return [
                'ok' => false,
                'status' => 500,
                'message' => 'No se encontro la plantilla CECH-SST.docx en storage/app/templates.',
            ];
        }

        $tempDir = storage_path('app/private/temp/actas');
        File::ensureDirectoryExists($tempDir);

        $baseFilename = sprintf('acta_rrhh_%d_%s', $solicitudId, now()->format('YmdHis'));
        $docxPath = $tempDir.DIRECTORY_SEPARATOR.$baseFilename.'.docx';
        $pdfPath = $tempDir.DIRECTORY_SEPARATOR.$baseFilename.'.pdf';

        $template = new TemplateProcessor($templatePath);
        $template->setValue('epp', $actaData['epp']);
        $template->setValue('usuario', $actaData['usuario']);
        $template->setValue('dni', $actaData['dni']);
        $template->setValue('area', $actaData['area']);
        $template->setValue('fecha', $actaData['fecha']);
        $template->saveAs($docxPath);

        $sofficeBinary = $this->resolveSofficeBinary();
        if ($sofficeBinary === null) {
            return [
                'ok' => false,
                'status' => 500,
                'message' => 'No se encontro LibreOffice. Configura SOFFICE_PATH en .env para convertir DOCX a PDF.',
            ];
        }

        $process = new Process([
            $sofficeBinary,
            '--headless',
            '--convert-to',
            'pdf',
            '--outdir',
            $tempDir,
            $docxPath,
        ]);
        $process->setTimeout(60);
        $process->run();

        if (! $process->isSuccessful() || ! File::exists($pdfPath)) {
            return [
                'ok' => false,
                'status' => 500,
                'message' => 'No se pudo convertir el acta a PDF. Verifica la instalacion de LibreOffice.',
            ];
        }

        return [
            'ok' => true,
            'status' => 200,
            'message' => 'Acta generada correctamente.',
            'pdf_path' => $pdfPath,
            'pdf_name' => 'acta_rrhh_'.$solicitudId.'.pdf',
        ];
    }

    /**
     * @return array{epp:string,usuario:string,dni:string,area:string,fecha:string}|null
     */
    protected function getActaTemplateData(ConnectionInterface $connection, int $id): ?array
    {
        $rows = $connection->select(
            <<<'SQL'
            SELECT
                s.id_solicitud,
                s.fecha_registro,
                u.firstname,
                u.lastname,
                u.dni,
                dep.departamento AS area
            FROM solicitudes s
            INNER JOIN ost_staff u ON u.staff_id = s.id_usuario_solicitante
            LEFT JOIN departamento dep ON dep.id_departamento = u.dept_id
            WHERE s.id_solicitud = ?
            LIMIT 1
            SQL,
            [$id]
        );

        if ($rows === []) {
            return null;
        }

        $row = $rows[0];
        $eppRows = $connection->select(
            <<<'SQL'
            SELECT DISTINCT p.descripcion AS epp
            FROM solicitud_detalles sd
            INNER JOIN inventario i ON i.id_inventario = sd.id_inventario
            INNER JOIN productos p ON p.id_producto = i.id_producto
            WHERE sd.id_solicitud = ?
              AND p.descripcion IS NOT NULL
              AND p.descripcion <> ''
            ORDER BY p.descripcion ASC
            SQL,
            [$id]
        );

        $eppList = collect($eppRows)
            ->pluck('epp')
            ->filter(fn ($value): bool => is_string($value) && trim($value) !== '')
            ->map(fn ($value): string => trim((string) $value))
            ->values()
            ->all();

        $fecha = isset($row->fecha_registro)
            ? Carbon::parse((string) $row->fecha_registro)->format('d/m/Y')
            : now()->format('d/m/Y');

        $usuario = trim((string) (($row->firstname ?? '').' '.($row->lastname ?? '')));

        return [
            'epp' => $eppList !== [] ? implode(', ', $eppList) : 'N/A',
            'usuario' => $usuario !== '' ? $usuario : 'N/A',
            'dni' => isset($row->dni) && trim((string) $row->dni) !== '' ? (string) $row->dni : 'N/A',
            'area' => isset($row->area) && trim((string) $row->area) !== '' ? (string) $row->area : 'N/A',
            'fecha' => $fecha,
        ];
    }

    protected function resolveSofficeBinary(): ?string
    {
        $configuredPath = trim((string) env('SOFFICE_PATH', ''));
        if ($configuredPath !== '' && File::exists($configuredPath)) {
            return $configuredPath;
        }

        $defaultPaths = [
            'C:\\Program Files\\LibreOffice\\program\\soffice.exe',
            'C:\\Program Files (x86)\\LibreOffice\\program\\soffice.exe',
        ];

        foreach ($defaultPaths as $path) {
            if (File::exists($path)) {
                return $path;
            }
        }

        return null;
    }
}
