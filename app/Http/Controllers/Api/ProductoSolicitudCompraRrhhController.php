<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

class ProductoSolicitudCompraRrhhController extends Controller
{
    use ApiResponseTrait;
    private const TIPO_SOLICITUD_COMPRA = 'COMPRA';
    private const TIPO_SOLICITUD_INTERNO = 'INTERNO';

    public function index(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'staff_id' => ['nullable', 'integer', 'min:1'],
                'id_producto' => ['nullable', 'integer', 'min:1'],
                'id_detalle_solicitud' => ['nullable', 'integer', 'min:1'],
                'id_solicitud' => ['nullable', 'integer', 'min:1'],
            ]);

            $query = DB::connection('mysql_external')
                ->table('producto_solicitud_compra_rrhh as pscr')
                ->leftJoin('productos as p', 'p.id_producto', '=', 'pscr.id_producto')
                ->leftJoin('ost_staff as os', 'os.staff_id', '=', 'pscr.staff_id')
                ->leftJoin('solicitud_detalles as sd', 'sd.id_detalle_solicitud', '=', 'pscr.id_detalle_solicitud')
                ->leftJoin('solicitudes as s', 's.id_solicitud', '=', 'sd.id_solicitud')
                ->select([
                    'pscr.id',
                    'pscr.id_producto',
                    'pscr.staff_id',
                    'pscr.id_detalle_solicitud',
                    'p.codigo_producto',
                    'p.descripcion as producto_descripcion',
                    'p.id_categoria',
                    'os.username',
                    'os.firstname',
                    'os.lastname',
                    'os.dept_id',
                    'sd.id_solicitud',
                    'sd.id_inventario',
                    'sd.cantidad_solicitada',
                    'sd.area_id',
                    'sd.id_estado_detalle',
                    'sd.observacion_atencion',
                    'sd.url_imagen',                       
                    's.fecha_registro as solicitud_fecha_registro',
                    's.fecha_necesaria as solicitud_fecha_necesaria',
                    's.prioridad as solicitud_prioridad',
                ]);

            if (isset($validated['staff_id'])) {
                $query->where('pscr.staff_id', (int) $validated['staff_id']);
            }

            if (isset($validated['id_producto'])) {
                $query->where('pscr.id_producto', (int) $validated['id_producto']);
            }

            if (isset($validated['id_detalle_solicitud'])) {
                $query->where('pscr.id_detalle_solicitud', (int) $validated['id_detalle_solicitud']);
            }

            if (isset($validated['id_solicitud'])) {
                $query->where('sd.id_solicitud', (int) $validated['id_solicitud']);
            }

            $query->whereIn('s.tipo_solicitud', [
                self::TIPO_SOLICITUD_COMPRA,
                self::TIPO_SOLICITUD_INTERNO,
            ]);

            $rows = $query
                ->orderByDesc('pscr.id')
                ->get()
                ->map(fn ($row): array => [
                    'id' => (int) $row->id,
                    'id_producto' => (int) $row->id_producto,
                    'staff_id' => (int) $row->staff_id,
                    'id_detalle_solicitud' => $row->id_detalle_solicitud !== null ? (int) $row->id_detalle_solicitud : null,
                    'producto' => [
                        'id_producto' => (int) $row->id_producto,
                        'codigo_producto' => $row->codigo_producto ?? null,
                        'descripcion' => $row->producto_descripcion ?? null,
                        'id_categoria' => $row->id_categoria !== null ? (int) $row->id_categoria : null,
                    ],
                    'staff' => [
                        'staff_id' => (int) $row->staff_id,
                        'username' => $row->username ?? null,
                        'firstname' => $row->firstname ?? null,
                        'lastname' => $row->lastname ?? null,
                        'full_name' => $this->formatStaffFullName($row),
                        'dept_id' => $row->dept_id !== null ? (int) $row->dept_id : null,
                    ],
                    'detalle' => [
                        'id_detalle_solicitud' => $row->id_detalle_solicitud !== null ? (int) $row->id_detalle_solicitud : null,
                        'id_solicitud' => $row->id_solicitud !== null ? (int) $row->id_solicitud : null,
                        'id_inventario' => $row->id_inventario !== null ? (int) $row->id_inventario : null,
                        'cantidad_solicitada' => $row->cantidad_solicitada !== null ? (float) $row->cantidad_solicitada : null,
                        'area_id' => $row->area_id !== null ? (int) $row->area_id : null,
                        'id_estado_detalle' => $row->id_estado_detalle !== null ? (int) $row->id_estado_detalle : null,
                        'observacion_atencion' => $row->observacion_atencion ?? null,
                        'url_imagen' => $row->url_imagen ?? null,
                    ],
                    'solicitud' => [
                        'id_solicitud' => $row->id_solicitud !== null ? (int) $row->id_solicitud : null,
                        'fecha_registro' => $row->solicitud_fecha_registro ?? null,
                        'fecha_necesaria' => $row->solicitud_fecha_necesaria ?? null,
                        'prioridad' => $row->solicitud_prioridad ?? null,
                    ],
                ])
                ->values()
                ->all();

            return $this->successResponse($rows, 'Registros consultados correctamente');
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('No se pudieron consultar los registros.', 500);
        }
    }

    protected function formatStaffFullName(object $row): ?string
    {
        $firstname = trim((string) ($row->firstname ?? ''));
        $lastname = trim((string) ($row->lastname ?? ''));
        $fullName = trim($firstname . ' ' . $lastname);

        return $fullName !== '' ? $fullName : null;
    }
}
