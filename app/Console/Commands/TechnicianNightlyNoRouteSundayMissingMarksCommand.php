<?php

namespace App\Console\Commands;

use App\Services\TechnicianNightlyMissingMarksService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class TechnicianNightlyNoRouteSundayMissingMarksCommand extends Command
{
    protected $signature = 'technicians:nightly-no-route-sunday-missing-marks {--date= : Fecha a procesar en formato Y-m-d} {--dni= : Filtro opcional por DNI}';

    protected $description = 'Genera y registra el concepto 4 para tecnicos sin ruta que no marcaron en domingo';

    private const DEFAULT_TIMEZONE = 'America/Lima';

    public function __construct(
        private readonly TechnicianNightlyMissingMarksService $service
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $fecha = (string) ($this->option('date') ?: now(self::DEFAULT_TIMEZONE)->format('Y-m-d'));
        $dni = $this->option('dni') ? (string) $this->option('dni') : null;

        try {
            $result = $this->service->processSundayNoRouteMissingConcepts($fecha, $dni);

            if (! empty($result['skipped_reason'])) {
                $this->warn(sprintf(
                    'Proceso sin ruta domingo omitido para %s: %s',
                    $result['preview']['fecha'],
                    $result['skipped_reason']
                ));
            } else {
                $this->info(sprintf(
                    'Proceso sin ruta domingo completado para %s. Procesados: %d. Fallidos: %d.',
                    $result['preview']['fecha'],
                    $result['processed_count'],
                    $result['failed_count']
                ));
            }

            Log::info('Proceso nocturno de tecnicos sin ruta domingo finalizado', [
                'fecha' => $result['preview']['fecha'],
                'dni' => $dni,
                'processed_count' => $result['processed_count'],
                'failed_count' => $result['failed_count'],
                'concept_id' => $result['concept_id'],
                'skipped_reason' => $result['skipped_reason'] ?? null,
            ]);

            if ($result['failed_count'] > 0) {
                return self::FAILURE;
            }

            return self::SUCCESS;
        } catch (\Throwable $e) {
            Log::error('Error ejecutando proceso nocturno de tecnicos sin ruta domingo', [
                'fecha' => $fecha,
                'dni' => $dni,
                'error' => $e->getMessage(),
            ]);

            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
