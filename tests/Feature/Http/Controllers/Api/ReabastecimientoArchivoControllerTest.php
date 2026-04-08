<?php

namespace Tests\Feature\Http\Controllers\Api;

use App\Http\Controllers\Api\ReabastecimientoController;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class ReabastecimientoArchivoControllerTest extends TestCase
{
    public function test_index_archivos_lists_history_for_solicitud(): void
    {
        $solicitudLookup = Mockery::mock();
        $query = Mockery::mock();
        $connection = Mockery::mock();

        $solicitudLookup->shouldReceive('select')->once()->andReturnSelf();
        $solicitudLookup->shouldReceive('where')->once()->with('sr.id_solicitud_reb', 3)->andReturnSelf();
        $solicitudLookup->shouldReceive('where')->once()->with('sr.id_area_solicitante', 11)->andReturnSelf();
        $solicitudLookup->shouldReceive('first')->once()->andReturn((object) [
            'id_solicitud_reb' => 3,
            'id_area_solicitante' => 11,
        ]);

        $query->shouldReceive('leftJoin')->once()->andReturnSelf();
        $query->shouldReceive('select')->once()->andReturnSelf();
        $query->shouldReceive('where')->once()->with('rl.id_solicitud_reb', 3)->andReturnSelf();
        $query->shouldReceive('orderByDesc')->once()->with('rl.fecha_creacion')->andReturnSelf();
        $query->shouldReceive('paginate')->once()->with(10, ['*'], 'page', 1)->andReturn(
            new LengthAwarePaginator([
                (object) [
                    'id_log_reb' => 1,
                    'id_solicitud_reb' => 3,
                    'id_usuario_comenta' => 182,
                    'comentario' => 'adjunto uno',
                    'archivo_ruta' => 'reabastecimiento/solicitudes/3/demo.pdf',
                    'archivo_nombre_original' => 'demo.pdf',
                    'fecha_creacion' => '2025-11-24 11:17:51',
                    'staff_id' => 182,
                    'staff_dept_id' => 11,
                    'staff_role_id' => 1,
                    'staff_username' => 'raul.castro',
                    'staff_firstname' => 'Hermes Raul',
                    'staff_lastname' => 'Castro Campos',
                ],
            ], 1, 10, 1, ['path' => '/'])
        );

        $connection->shouldReceive('table')
            ->with('solicitudes_reabastecimiento as sr')
            ->once()
            ->andReturn($solicitudLookup);

        $connection->shouldReceive('table')
            ->with('reabastecimiento_log as rl')
            ->once()
            ->andReturn($query);

        $controller = $this->makeController($connection);

        $request = Request::create('/api/reabastecimiento/solicitudes/3/archivos', 'GET', [
            'page' => 1,
            'per_page' => 10,
        ]);

        $response = $controller->indexArchivos($request, 3);
        $payload = $response->getData(true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue($payload['success']);
        $this->assertCount(1, $payload['data']['data']);
        $this->assertSame(1, $payload['data']['data'][0]['id_log_reb']);
        $this->assertSame('adjunto uno', $payload['data']['data'][0]['comentario']);
    }

    public function test_store_archivo_creates_log_and_stores_file(): void
    {
        $solicitudLookup = Mockery::mock();
        $insertBuilder = Mockery::mock();
        $disk = Mockery::mock();
        $connection = Mockery::mock();

        $solicitudLookup->shouldReceive('select')->once()->andReturnSelf();
        $solicitudLookup->shouldReceive('where')->once()->with('sr.id_solicitud_reb', 3)->andReturnSelf();
        $solicitudLookup->shouldReceive('where')->once()->with('sr.id_area_solicitante', 11)->andReturnSelf();
        $solicitudLookup->shouldReceive('first')->once()->andReturn((object) [
            'id_solicitud_reb' => 3,
            'id_area_solicitante' => 11,
        ]);

        $insertBuilder->shouldReceive('insertGetId')->once()->with(Mockery::on(function (array $payload): bool {
            return $payload['id_solicitud_reb'] === 3
                && $payload['id_usuario_comenta'] === 182
                && $payload['comentario'] === 'adjunto uno'
                && ! empty($payload['archivo_ruta'])
                && $payload['archivo_nombre_original'] === 'demo.pdf'
                && array_key_exists('fecha_creacion', $payload);
        }))->andReturn(7);

        $disk->shouldReceive('putFileAs')->once()->andReturn('reabastecimiento/solicitudes/3/demo.pdf');
        Storage::shouldReceive('disk')->with('public')->andReturn($disk);

        $connection->shouldReceive('table')
            ->with('solicitudes_reabastecimiento as sr')
            ->once()
            ->andReturn($solicitudLookup);

        $connection->shouldReceive('table')
            ->with('reabastecimiento_log')
            ->once()
            ->andReturn($insertBuilder);

        $connection->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function (callable $callback) {
                return $callback();
            });

        $controller = $this->makeController($connection);
        $file = UploadedFile::fake()->create('demo.pdf', 100, 'application/pdf');

        $request = Request::create('/api/reabastecimiento/solicitudes/3/archivos', 'POST', [
            'id_usuario_comenta' => 182,
            'comentario' => 'adjunto uno',
        ], [], [
            'archivo' => $file,
        ]);

        $response = $controller->storeArchivo($request, 3);
        $payload = $response->getData(true);

        $this->assertSame(201, $response->getStatusCode());
        $this->assertTrue($payload['success']);
        $this->assertSame(7, $payload['data']['id_log_reb']);
        $this->assertSame(3, $payload['data']['id_solicitud_reb']);
        $this->assertSame(182, $payload['data']['id_usuario_comenta']);
        $this->assertSame('adjunto uno', $payload['data']['comentario']);
    }

    public function test_destroy_archivo_deletes_row_and_file(): void
    {
        $path = 'reabastecimiento/solicitudes/3/demo.pdf';

        $lookup = Mockery::mock();
        $deleteBuilder = Mockery::mock();
        $disk = Mockery::mock();
        $connection = Mockery::mock();

        $lookup->shouldReceive('join')->once()->andReturnSelf();
        $lookup->shouldReceive('select')->once()->andReturnSelf();
        $lookup->shouldReceive('where')->once()->with('rl.id_log_reb', 7)->andReturnSelf();
        $lookup->shouldReceive('where')->once()->with('sr.id_area_solicitante', 11)->andReturnSelf();
        $lookup->shouldReceive('first')->once()->andReturn((object) [
            'id_log_reb' => 7,
            'id_solicitud_reb' => 3,
            'id_usuario_comenta' => 182,
            'comentario' => 'adjunto uno',
            'archivo_ruta' => $path,
            'archivo_nombre_original' => 'demo.pdf',
            'fecha_creacion' => '2025-11-24 11:17:51',
        ]);

        $deleteBuilder->shouldReceive('where')->once()->with('id_log_reb', 7)->andReturnSelf();
        $deleteBuilder->shouldReceive('delete')->once()->andReturn(1);

        $disk->shouldReceive('exists')->once()->with($path)->andReturn(true);
        $disk->shouldReceive('delete')->once()->with($path)->andReturn(true);
        Storage::shouldReceive('disk')->with('public')->andReturn($disk);

        $connection->shouldReceive('table')
            ->with('reabastecimiento_log as rl')
            ->once()
            ->andReturn($lookup);

        $connection->shouldReceive('table')
            ->with('reabastecimiento_log')
            ->once()
            ->andReturn($deleteBuilder);

        $connection->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function (callable $callback) {
                return $callback();
            });

        $controller = $this->makeController($connection);

        $response = $controller->destroyArchivo(7);
        $payload = $response->getData(true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue($payload['success']);
        $this->assertSame(7, $payload['data']['id_log_reb']);
        $this->assertSame(3, $payload['data']['id_solicitud_reb']);
    }

    private function makeController($connection): ReabastecimientoController
    {
        return new class($connection) extends ReabastecimientoController
        {
            public function __construct(private $connection) {}

            protected function getConnection()
            {
                return $this->connection;
            }
        };
    }
}
