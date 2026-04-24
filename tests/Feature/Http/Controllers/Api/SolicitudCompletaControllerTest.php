<?php

namespace Tests\Feature\Http\Controllers\Api;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Mockery;
use Tests\TestCase;

class SolicitudCompletaControllerTest extends TestCase
{
    public function test_registrar_completa_creates_multi_area_solicitud_and_uploads_files(): void
    {
        Mail::fake();
        config(['session.driver' => 'array']);

        $connection = Mockery::mock();
        $solicitudesTable = Mockery::mock();
        $detallesTable = Mockery::mock();
        $areasTable = Mockery::mock();
        $flujoTable = Mockery::mock();
        $disk = Mockery::mock();

        DB::shouldReceive('connection')
            ->with('mysql_external')
            ->andReturn($connection);

        \Illuminate\Support\Facades\Storage::shouldReceive('disk')
            ->with('public')
            ->andReturn($disk);

        $connection->shouldReceive('select')
            ->andReturnUsing(function (string $sql, array $bindings): array {
                if (str_contains($sql, 'FROM ost_staff') && str_contains($sql, 'WHERE staff_id = ?')) {
                    return [
                        (object) [
                            'staff_id' => 163,
                            'dept_id' => 11,
                            'firstname' => 'Alexander',
                            'lastname' => 'Flores',
                            'email' => 'alexander@example.com',
                            'role_id' => 1,
                        ],
                    ];
                }

                if (str_contains($sql, 'FROM inventario i') && $bindings === [101]) {
                    return [
                        (object) [
                            'id_inventario' => 101,
                            'id_area' => 11,
                            'id_producto' => 501,
                            'producto' => 'Toner',
                            'requiere_foto_producto_anterior' => 0,
                            'descripcion_area' => 'RR.HH.',
                        ],
                    ];
                }

                if (str_contains($sql, 'FROM inventario i') && $bindings === [202]) {
                    return [
                        (object) [
                            'id_inventario' => 202,
                            'id_area' => 12,
                            'id_producto' => 502,
                            'producto' => 'Guantes',
                            'requiere_foto_producto_anterior' => 1,
                            'descripcion_area' => 'LogÃƒÂ­stica',
                        ],
                    ];
                }

                if (str_contains($sql, 'SELECT DISTINCT os.email')) {
                    return [
                        (object) ['email' => 'rrhh1@example.com'],
                        (object) ['email' => 'logistica1@example.com'],
                    ];
                }

                return [];
            });

        $connection->shouldReceive('transaction')
            ->twice()
            ->andReturnUsing(function (callable $callback) {
                return $callback();
            });

        $connection->shouldReceive('table')
            ->with('solicitudes')
            ->once()
            ->andReturn($solicitudesTable);

        $connection->shouldReceive('table')
            ->with('solicitud_detalles')
            ->twice()
            ->andReturn($detallesTable);

        $connection->shouldReceive('table')
            ->with('solicitud_areas')
            ->once()
            ->andReturn($areasTable);

        $connection->shouldReceive('table')
            ->with('solicitud_flujo_aprobaciones')
            ->once()
            ->andReturn($flujoTable);

        $solicitudesTable->shouldReceive('insertGetId')
            ->once()
            ->with(Mockery::on(function (array $payload): bool {
                return $payload['id_usuario_solicitante'] === 163
                    && $payload['id_area_origen'] === 11
                    && $payload['id_estado_general'] === 11
                    && $payload['prioridad'] === 'Alta'
                    && $payload['tipo_entrega_preferida'] === 'Directo'
                    && $payload['id_direccion_entrega'] === 5
                    && $payload['ubicacion'] === 'PROVINCIA'
                    && $payload['es_pedido_compra'] === 0
                    && $payload['pedido_compra_estado'] === 0
                    && $payload['tipo_solicitud'] === 'MIXTO'
                    && $payload['justificacion'] === 'Pedido para el ÃƒÂ¡rea'
                    && array_key_exists('fecha_registro', $payload);
            }))
            ->andReturn(42);

        $detallesTable->shouldReceive('insert')
            ->once()
            ->with(Mockery::on(function (array $rows): bool {
                return count($rows) === 2
                    && $rows[0]['id_solicitud'] === 42
                    && $rows[0]['id_inventario'] === 101
                    && $rows[0]['cantidad_solicitada'] === 2
                    && $rows[0]['observacion_atencion'] === null
                    && $rows[0]['area_id'] === 11
                    && $rows[0]['ruta_imagen'] === null
                    && $rows[0]['url_imagen'] === null
                    && $rows[1]['id_inventario'] === 202
                    && $rows[1]['cantidad_solicitada'] === 1
                    && $rows[1]['observacion_atencion'] === 'Nota Usuario: Revisar estado'
                    && $rows[1]['area_id'] === 12
                    && $rows[1]['ruta_imagen'] === null
                    && $rows[1]['url_imagen'] === null;
            }))
            ->andReturnTrue();

        $detallesTable->shouldReceive('where')
            ->once()
            ->with('id_solicitud', 42)
            ->andReturnSelf();

        $detallesTable->shouldReceive('where')
            ->once()
            ->with('id_inventario', 202)
            ->andReturnSelf();

        $detallesTable->shouldReceive('update')
            ->once()
            ->with(Mockery::on(function (array $payload): bool {
                return str_starts_with($payload['ruta_imagen'], 'uploads/solicitudes/42/sol_42_inv_202_')
                    && str_ends_with($payload['ruta_imagen'], '.jpg')
                    && str_contains($payload['url_imagen'], '/storage/uploads/solicitudes/42/');
            }))
            ->andReturnTrue();

        $areasTable->shouldReceive('insert')
            ->once()
            ->with(Mockery::on(function (array $rows): bool {
                return count($rows) === 2
                    && $rows[0]['id_solicitud'] === 42
                    && $rows[0]['id_area'] === 11
                    && $rows[0]['id_estado_area'] === 11
                    && array_key_exists('fecha_recepcion', $rows[0])
                    && $rows[1]['id_area'] === 12;
            }))
            ->andReturnTrue();

        $disk->shouldReceive('put')
            ->once()
            ->with(Mockery::on(function (string $path): bool {
                return str_starts_with($path, 'uploads/solicitudes/42/sol_42_inv_202_')
                    && str_ends_with($path, '.jpg');
            }), Mockery::type('string'))
            ->andReturnTrue();

        $flujoTable->shouldReceive('insertGetId')
            ->once()
            ->with(Mockery::on(function (array $payload): bool {
                return $payload['id_solicitud'] === 42
                    && $payload['id_area_responsable'] === 11
                    && $payload['id_usuario_asignado'] === 163
                    && $payload['id_estado'] === 11
                    && preg_match('/Solicitud creada con\\s*2/i', (string) $payload['comentarios']) === 1
                    && preg_match('/Derivada a\\s*2/i', (string) $payload['comentarios']) === 1
                    && array_key_exists('fecha_actualizacion', $payload);
            }))
            ->andReturn(88);

        $file = UploadedFile::fake()->image('rrhh.jpg');

        $response = $this->withoutMiddleware()->post('/api/solicitudes/registrar-completa', [
            'id_usuario_solicitante' => 163,
            'justificacion' => 'Pedido para el ÃƒÂ¡rea',
            'es_pedido_compra' => '0',
            'ubicacion' => 'PROVINCIA',
            'prioridad' => 'Alta',
            'fecha_necesaria' => '2026-04-20',
            'tipo_entrega_preferida' => 'Directo',
            'id_direccion_entrega' => 5,
            'id_producto_insumos' => [101],
            'cantidad_insumos' => [2],
            'observacion_insumos' => [''],
            'id_producto_rrhh' => [202],
            'cantidad_rrhh' => [1],
            'observacion_rrhh' => ['Revisar estado'],
            'id_area' => [11, 12],
            'foto_rrhh' => [$file],
        ]);

        $payload = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(201, $response->getStatusCode());
        $this->assertTrue($payload['success']);
        $this->assertSame('Solicitud registrada correctamente.', $payload['message']);
        $this->assertSame('SOL-000042', $payload['ticket']);
        $this->assertSame(['SOL-000042'], $payload['tickets']);
        $this->assertCount(1, $payload['uploaded_files']);
        $this->assertStringStartsWith('uploads/solicitudes/42/', $payload['uploaded_files'][0]['path']);
        $this->assertStringStartsWith('http', $payload['uploaded_files'][0]['url']);

        Mail::assertNothingSent();
    }

    public function test_registrar_completa_fails_when_required_photo_is_missing(): void
    {
        Mail::fake();
        config(['session.driver' => 'array']);

        $connection = Mockery::mock();

        DB::shouldReceive('connection')
            ->with('mysql_external')
            ->andReturn($connection);

        $connection->shouldReceive('select')
            ->andReturnUsing(function (string $sql, array $bindings): array {
                if (str_contains($sql, 'FROM ost_staff') && str_contains($sql, 'WHERE staff_id = ?')) {
                    return [
                        (object) [
                            'staff_id' => 163,
                            'dept_id' => 11,
                            'firstname' => 'Alexander',
                            'lastname' => 'Flores',
                            'email' => 'alexander@example.com',
                            'role_id' => 1,
                        ],
                    ];
                }

                if (str_contains($sql, 'FROM inventario i') && $bindings === [202]) {
                    return [
                        (object) [
                            'id_inventario' => 202,
                            'id_area' => 12,
                            'id_producto' => 502,
                            'producto' => 'Guantes',
                            'requiere_foto_producto_anterior' => 1,
                            'descripcion_area' => 'LogÃƒÂ­stica',
                        ],
                    ];
                }

                return [];
            });

        $connection->shouldReceive('transaction')->never();
        $connection->shouldReceive('table')->never();

        $response = $this->withoutMiddleware()->post('/api/solicitudes/registrar-completa', [
            'id_usuario_solicitante' => 163,
            'justificacion' => 'Pedido con foto obligatoria',
            'es_pedido_compra' => '0',
            'ubicacion' => 'PROVINCIA',
            'prioridad' => 'Media',
            'id_producto_rrhh' => [202],
            'cantidad_rrhh' => [1],
            'observacion_rrhh' => ['Revisar'],
            'id_area' => [12],
        ]);

        $payload = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertFalse($payload['success']);
        $this->assertStringContainsString('requiere foto', $payload['message']);

        Mail::assertNothingSent();
    }

    public function test_registrar_completa_lima_separa_area_11_no_pscr_como_solicitud_interna(): void
    {
        Mail::fake();
        config(['session.driver' => 'array']);

        $connection = Mockery::mock();
        $solicitudesTable = Mockery::mock();
        $detallesTable = Mockery::mock();
        $areasTable = Mockery::mock();
        $flujoTable = Mockery::mock();
        $disk = Mockery::mock();

        $payloadsSolicitudes = [];
        $rowsDetalles = [];

        DB::shouldReceive('connection')
            ->with('mysql_external')
            ->andReturn($connection);

        \Illuminate\Support\Facades\Storage::shouldReceive('disk')
            ->with('public')
            ->andReturn($disk);

        $disk->shouldReceive('put')
            ->withAnyArgs()
            ->andReturnTrue();

        $connection->shouldReceive('select')
            ->andReturnUsing(function (string $sql, array $bindings): array {
                if (str_contains($sql, 'FROM ost_staff') && str_contains($sql, 'WHERE staff_id = ?')) {
                    return [
                        (object) [
                            'staff_id' => 163,
                            'dept_id' => 11,
                            'firstname' => 'Alexander',
                            'lastname' => 'Flores',
                            'email' => 'alexander@example.com',
                            'role_id' => 1,
                        ],
                    ];
                }

                if (str_contains($sql, 'FROM inventario i') && $bindings === [101]) {
                    return [
                        (object) [
                            'id_inventario' => 101,
                            'id_area' => 11,
                            'id_producto' => 501,
                            'producto' => 'Toner',
                            'requiere_foto_producto_anterior' => 0,
                            'descripcion_area' => 'RR.HH.',
                        ],
                    ];
                }

                if (str_contains($sql, 'FROM inventario i') && $bindings === [202]) {
                    return [
                        (object) [
                            'id_inventario' => 202,
                            'id_area' => 12,
                            'id_producto' => 502,
                            'producto' => 'Guantes',
                            'requiere_foto_producto_anterior' => 0,
                            'descripcion_area' => 'Logistica',
                        ],
                    ];
                }

                if (str_contains($sql, 'SELECT id_detalle_solicitud, id_inventario')) {
                    return [];
                }

                if (str_contains($sql, 'SELECT DISTINCT os.email')) {
                    return [];
                }

                return [];
            });

        $connection->shouldReceive('transaction')
            ->times(2)
            ->andReturnUsing(function (callable $callback) {
                return $callback();
            });

        $connection->shouldReceive('table')
            ->with('solicitudes')
            ->twice()
            ->andReturn($solicitudesTable);

        $connection->shouldReceive('table')
            ->with('solicitud_detalles')
            ->twice()
            ->andReturn($detallesTable);

        $connection->shouldReceive('table')
            ->with('solicitud_areas')
            ->twice()
            ->andReturn($areasTable);

        $connection->shouldReceive('table')
            ->with('solicitud_flujo_aprobaciones')
            ->twice()
            ->andReturn($flujoTable);

        $solicitudesTable->shouldReceive('insertGetId')
            ->twice()
            ->with(Mockery::on(function (array $payload) use (&$payloadsSolicitudes): bool {
                $payloadsSolicitudes[] = $payload;

                return true;
            }))
            ->andReturn(61, 62);

        $detallesTable->shouldReceive('insert')
            ->twice()
            ->with(Mockery::on(function (array $rows) use (&$rowsDetalles): bool {
                $rowsDetalles[] = $rows;

                return count($rows) === 1;
            }))
            ->andReturnTrue();

        $detallesTable->shouldReceive('where')->never();
        $detallesTable->shouldReceive('update')->never();

        $areasTable->shouldReceive('insert')
            ->twice()
            ->with(Mockery::on(function (array $rows): bool {
                return count($rows) === 1;
            }))
            ->andReturnTrue();

        $flujoTable->shouldReceive('insertGetId')
            ->twice()
            ->andReturn(91, 92);

        $response = $this->withoutMiddleware()->post('/api/solicitudes/registrar-completa', [
            'id_usuario_solicitante' => 163,
            'justificacion' => 'Separar area 11',
            'es_pedido_compra' => '0',
            'ubicacion' => 'LIMA',
            'prioridad' => 'Media',
            'id_producto_insumos' => [101],
            'cantidad_insumos' => [2],
            'observacion_insumos' => [''],
            'id_producto_rrhh' => [202],
            'cantidad_rrhh' => [1],
            'observacion_rrhh' => [''],
            'id_area' => [11, 12],
        ]);

        $payload = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(201, $response->getStatusCode());
        $this->assertTrue($payload['success']);
        $this->assertSame('SOL-000061', $payload['ticket']);
        $this->assertSame(['SOL-000061', 'SOL-000062'], $payload['tickets']);
        $this->assertSame([], $payload['uploaded_files']);
        $this->assertCount(2, $payloadsSolicitudes);
        $this->assertCount(2, $rowsDetalles);

        $this->assertSame('INTERNO', $payloadsSolicitudes[0]['tipo_solicitud']);
        $this->assertSame(0, $payloadsSolicitudes[0]['es_pedido_compra']);
        $this->assertSame('MIXTO', $payloadsSolicitudes[1]['tipo_solicitud']);
        $this->assertSame(0, $payloadsSolicitudes[1]['es_pedido_compra']);

        $this->assertSame(101, $rowsDetalles[0][0]['id_inventario']);
        $this->assertSame(11, $rowsDetalles[0][0]['area_id']);
        $this->assertSame(202, $rowsDetalles[1][0]['id_inventario']);
        $this->assertSame(12, $rowsDetalles[1][0]['area_id']);
    }

    public function test_registrar_completa_pscr_creates_solicitud_gasto_record(): void
    {
        Mail::fake();
        config([
            'session.driver' => 'array',
            'services.solicitudes.pedido_compra_notify_email' => 'compras@example.com',
        ]);

        $connection = Mockery::mock();
        $solicitudesGastoTable = Mockery::mock();
        $gastoDetallesTable = Mockery::mock();
        $disk = Mockery::mock();

        DB::shouldReceive('connection')
            ->with('mysql_external')
            ->andReturn($connection);

        \Illuminate\Support\Facades\Storage::shouldReceive('disk')
            ->with('public')
            ->andReturn($disk);

        $disk->shouldReceive('put')
            ->withAnyArgs()
            ->andReturnTrue();

        $connection->shouldReceive('select')
            ->andReturnUsing(function (string $sql, array $bindings): array {
                if (str_contains($sql, 'FROM ost_staff') && str_contains($sql, 'WHERE staff_id = ?')) {
                    return [
                        (object) [
                            'staff_id' => 163,
                            'dept_id' => 11,
                            'firstname' => 'Alexander',
                            'lastname' => 'Flores',
                            'email' => 'alexander@example.com',
                            'role_id' => 1,
                        ],
                    ];
                }

                if (str_contains($sql, 'FROM inventario i') && $bindings === [195]) {
                    return [
                        (object) [
                            'id_inventario' => 195,
                            'id_area' => 11,
                            'id_producto' => 195,
                            'producto' => 'Producto PSCR',
                            'requiere_foto_producto_anterior' => 0,
                            'descripcion_area' => 'RR.HH.',
                        ],
                    ];
                }

                return [];
            });

        $connection->shouldReceive('transaction')
            ->twice()
            ->andReturnUsing(function (callable $callback) {
                return $callback();
            });

        $connection->shouldReceive('table')
            ->with('solicitudes_gasto')
            ->once()
            ->andReturn($solicitudesGastoTable);

        $connection->shouldReceive('table')
            ->with('solicitud_gasto_detalles')
            ->twice()
            ->andReturn($gastoDetallesTable);

        $solicitudesGastoTable->shouldReceive('insertGetId')
            ->once()
            ->with(Mockery::on(function (array $payload): bool {
                return $payload['staff_id'] === 163
                    && $payload['id_area'] === 7
                    && $payload['motivo'] === 'Pedido PSCR | PSCR: 195'
                    && $payload['monto_estimado'] === 0.0
                    && $payload['monto_real'] === 0.0
                    && $payload['estado'] === 'pendiente'
                    && array_key_exists('fecha_solicitud', $payload);
            }))
            ->andReturn(501);

        $gastoDetallesTable->shouldReceive('insert')
            ->once()
            ->with(Mockery::on(function (array $rows): bool {
                return count($rows) === 1
                    && $rows[0]['solicitud_gasto_id'] === 501
                    && $rows[0]['id_producto'] === 195
                    && $rows[0]['cantidad'] === 1
                    && $rows[0]['precio_estimado'] === 0
                    && $rows[0]['precio_real'] === 0
                    && $rows[0]['ruta_imagen'] === null;
            }))
            ->andReturnTrue();

        $gastoDetallesTable->shouldReceive('where')
            ->once()
            ->with('solicitud_gasto_id', 501)
            ->andReturnSelf();

        $gastoDetallesTable->shouldReceive('where')
            ->once()
            ->with('id_producto', 195)
            ->andReturnSelf();

        $gastoDetallesTable->shouldReceive('update')
            ->once()
            ->with(Mockery::on(function (array $payload): bool {
                return str_starts_with($payload['ruta_imagen'], 'uploads/solicitudes_gasto/501/sol_501_inv_195_')
                    && str_ends_with($payload['ruta_imagen'], '.jpg');
            }))
            ->andReturnTrue();

        $pscrFile = UploadedFile::fake()->image('pscr.jpg');

        $response = $this->withoutMiddleware()->post('/api/solicitudes/registrar-completa', [
            'id_usuario_solicitante' => 163,
            'justificacion' => 'Pedido PSCR',
            'es_pedido_compra' => '1',
            'ubicacion' => 'LIMA',
            'prioridad' => 'Alta',
            'id_producto_rrhh' => [195],
            'cantidad_rrhh' => [1],
            'observacion_rrhh' => [''],
            'id_area' => [11],
            'foto_rrhh' => [$pscrFile],
        ]);

        $payload = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(201, $response->getStatusCode());
        $this->assertTrue($payload['success']);
        $this->assertSame('GAS-000501', $payload['ticket']);
        $this->assertSame(['GAS-000501'], $payload['tickets']);
        $this->assertCount(1, $payload['uploaded_files']);
        $this->assertStringStartsWith('uploads/solicitudes_gasto/501/', $payload['uploaded_files'][0]['path']);
        $this->assertStringStartsWith('http', $payload['uploaded_files'][0]['url']);
        Mail::assertNothingSent();
    }
}
