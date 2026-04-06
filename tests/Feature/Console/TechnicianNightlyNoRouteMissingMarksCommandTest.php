<?php

namespace Tests\Feature\Console;

use App\Services\TechnicianNightlyMissingMarksService;
use Tests\TestCase;

class TechnicianNightlyNoRouteMissingMarksCommandTest extends TestCase
{
    public function test_command_processes_no_route_missing_marks_with_date_and_dni(): void
    {
        $mock = $this->createMock(TechnicianNightlyMissingMarksService::class);
        $mock->expects($this->once())
            ->method('processNoRouteMissingConcepts')
            ->with('2026-04-05', '12345678')
            ->willReturn([
                'preview' => [
                    'fecha' => '2026-04-05',
                ],
                'processed_count' => 1,
                'failed_count' => 0,
                'processed_users' => [],
                'failed_users' => [],
                'concept_id' => 1,
            ]);

        $this->app->instance(TechnicianNightlyMissingMarksService::class, $mock);

        $this->artisan('technicians:nightly-no-route-missing-marks', [
            '--date' => '2026-04-05',
            '--dni' => '12345678',
        ])
            ->expectsOutput('Proceso sin ruta completado para 2026-04-05. Procesados: 1. Fallidos: 0.')
            ->assertExitCode(0);
    }
}
