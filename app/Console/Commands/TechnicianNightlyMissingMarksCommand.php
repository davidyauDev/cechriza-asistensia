<?php

namespace App\Console\Commands;

use App\Services\TechnicianNightlyMissingMarksService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class TechnicianNightlyMissingMarksCommand extends Command
{
    protected $signature = 'technicians:nightly-missing-marks {--date= : Fecha a procesar en formato Y-m-d} {--dni= : Filtro opcional por DNI}';

    protected $description = 'Genera y registra el concepto 5 para tecnicos con ruta que no marcaron';

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
            $result = $this->service->processMissingConcepts($fecha, $dni);

            $this->info(sprintf(
                'Proceso completado para %s. Procesados: %d. Fallidos: %d.',
                $result['preview']['fecha'],
                $result['processed_count'],
                $result['failed_count']
            ));

            Log::info('Proceso nocturno de tecnicos finalizado', [
                'fecha' => $result['preview']['fecha'],
                'dni' => $dni,
                'processed_count' => $result['processed_count'],
                'failed_count' => $result['failed_count'],
                'concept_id' => $result['concept_id'],
            ]);

            if ($result['failed_count'] > 0) {
                return self::FAILURE;
            }

            return self::SUCCESS;
        } catch (\Throwable $e) {
            Log::error('Error ejecutando proceso nocturno de tecnicos', [
                'fecha' => $fecha,
                'dni' => $dni,
                'error' => $e->getMessage(),
            ]);

            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
