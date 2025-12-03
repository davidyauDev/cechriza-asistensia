<?php

namespace App\Http\Controllers\Api;

use App\Exports\DetalleAsistenciaExport;
use App\Exports\ResumenAsistenciaExport;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;;

class ReporteAsistenciaController extends Controller
{

    public function detalleMarcacion(Request $request)
    {
        $fecha = $request->input('fecha', date('Y-m-d'));
        $excluir = $request->input('excluir', ['6638042', '7791208']);
        $departmentId = $request->input('department_id');
        $empleadoId = $request->input('empleado_id');

        $whereDept = "";
        $paramsDept = [];
        if (!empty($departmentId)) {
            $whereDept = " AND pe.department_id = ? ";
            $paramsDept[] = $departmentId;
        }

        $whereUsuario = "";
        $paramsUsuario = [];
        if (!empty($empleadoId)) {
            $whereUsuario = " AND pe.id = ? ";
            $paramsUsuario[] = $empleadoId;
        }

        $sql = '
        WITH horarios AS (
            SELECT 
                aa.employee_id,
                ati.in_time AS horario
            FROM att_attschedule aa
            INNER JOIN att_attshift ash ON ash.id = aa.shift_id
            INNER JOIN att_shiftdetail ashd ON ashd.shift_id = ash.id
            INNER JOIN att_timeinterval ati ON ati.id = ashd.time_interval_id
            WHERE ashd.day_index + 1 = cast(to_char(?::date, \'D\') AS INT)
        ),
        marcaciones AS (
            SELECT
                it.emp_code,
                MIN(CAST(it.punch_time AS time)) AS ingreso,
                CASE 
                    WHEN MIN(CAST(it.punch_time AS time)) = MAX(CAST(it.punch_time AS time))
                    THEN NULL
                    ELSE MAX(CAST(it.punch_time AS time))
                END AS salida
            FROM iclock_transaction it
            WHERE date(it.punch_time) = ?
            GROUP BY it.emp_code
        )

        SELECT 
            pe.emp_code AS "DNI",
            pe.last_name AS "Apellidos",
            pe.first_name AS "Nombres",
            pd.dept_name AS "Departamento",
            pc.company_name AS "Empresa",
            h.horario AS "Horario",
            m.ingreso AS "Ingreso",
            m.salida AS "Salida",

            -- TARDANZA
            CASE 
                WHEN m.ingreso IS NOT NULL AND h.horario IS NOT NULL AND m.ingreso > h.horario 
                    THEN 1 
                ELSE 0 
            END AS "Tardanza",

            -- AUSENCIA
            CASE 
                WHEN m.ingreso IS NULL THEN 1 ELSE 0
            END AS "Ausencia"

        FROM personnel_employee pe
        INNER JOIN personnel_department pd ON pe.department_id = pd.id
        INNER JOIN personnel_company pc ON pd.company_id = pc.id

        LEFT JOIN horarios h ON h.employee_id = pe.id
        LEFT JOIN marcaciones m ON m.emp_code = pe.emp_code

        WHERE pe.status = 0
          AND pe.emp_code NOT IN (' . implode(',', array_fill(0, count($excluir), '?')) . ')
          ' . $whereDept . '
          ' . $whereUsuario . '

        ORDER BY pc.company_name, pd.dept_name, pe.last_name, pe.first_name
    ';

        $params = [
            $fecha, // horarios 
            $fecha, // marcaciones 
        ];

        $params = array_merge($params, $excluir);

        if (!empty($departmentId)) {
            $params = array_merge($params, $paramsDept);
        }

        if (!empty($empleadoId)) {
            $params = array_merge($params, $paramsUsuario);
        }

        $data = DB::connection('pgsql_external')->select($sql, $params);
        $asistencias = 0;
        $ausencias = 0;
        $tardanzas = 0;

        foreach ($data as $row) {
            if ($row->Ingreso !== null) $asistencias++;
            if ($row->Ausencia == 1) $ausencias++;
            if ($row->Tardanza == 1) $tardanzas++;
        }
        return response()->json([
            "data" => $data,
            "resumen" => [
                "asistencias" => $asistencias,
                "ausencias" => $ausencias,
                "tardanzas" => $tardanzas,
            ]
        ]);
    }



    public function detalleAsist(Request $request)
    {

        $fechaInicio = $request->input('fecha_inicio', Carbon::now()->startOfMonth()->format('Y-m-d'));
        $fechaFin = $request->input('fecha_fin', Carbon::now()->endOfMonth()->format('Y-m-d'));

        $empresaIds = $request->input('empresa_ids', [1,2]);
        if (!is_array($empresaIds)) $empresaIds = [$empresaIds];
        $empresaIdsPG = '{' . implode(',', $empresaIds) . '}';

        $departamentoIds = $request->input('departamento_ids', [8]);
        if (!is_array($departamentoIds)) $departamentoIds = [$departamentoIds];
        $departamentoIdsPG = '{' . implode(',', $departamentoIds) . '}';

        $usuarioIds = $request->input('usuarios', []);
        $whereUsuarios = '';

        if (!empty($usuarioIds)) {
            if (!is_array($usuarioIds)) $usuarioIds = [$usuarioIds];

            $usuariosPG = '{' . implode(',', $usuarioIds) . '}';
            $whereUsuarios = " AND pe.id = ANY(?) ";
        }


        ds($fechaInicio);
        ds($fechaFin);

        $sql = "
        SELECT
            pe.emp_code AS dni,
            pe.last_name AS apellidos,
            pe.first_name AS nombres,
            pd.dept_name AS departamento,
            pc.company_name AS empresa,
            ap.att_date AS fecha,
            CAST(ap.check_in AS time) AS h_ingreso,
            CAST(ap.check_out AS time) AS h_salida,
            CAST(ap.clock_in AS time) AS m_ingreso,
            CAST(ap.clock_out AS time) AS m_salida,
            TO_CHAR(make_interval(secs => NULLIF(ap.late, 0)), 'HH24:MI:SS') AS tardanza,
            TO_CHAR(
                make_interval(secs =>
                    CASE
                        WHEN ap.weekday = 5 AND ap.clock_out > ap.check_out
                            THEN ap.actual_worked + (EXTRACT(EPOCH FROM ap.clock_out) - EXTRACT(EPOCH FROM ap.check_out))
                        WHEN ap.weekday = 5 AND ap.clock_out < ap.check_out
                            THEN ap.actual_worked
                        WHEN ap.clock_out > ap.check_out
                            THEN ap.actual_worked + (EXTRACT(EPOCH FROM ap.clock_out) - EXTRACT(EPOCH FROM ap.check_out))
                        ELSE ap.actual_worked + ap.early_leave
                    END
                ),
                'HH24:MI:SS'
            ) AS total_trabajado
        FROM personnel_employee pe
        INNER JOIN att_payloadbase ap ON pe.id = ap.emp_id
        INNER JOIN personnel_department pd ON pe.department_id = pd.id
        INNER JOIN personnel_company pc ON pd.company_id = pc.id
        WHERE pe.status = 0
            AND pc.id = ANY(?)
            AND pd.id = ANY(?)
            $whereUsuarios
            AND CAST(ap.clock_in AS date) BETWEEN ? AND ?
        ORDER BY
            pd.dept_name,
            pe.last_name,
            ap.att_date
    ";

        $params = [$empresaIdsPG, $departamentoIdsPG];

        if (!empty($usuarioIds)) {
            $params[] = $usuariosPG;
        }

        $params[] = $fechaInicio;
        $params[] = $fechaFin;

        $result = DB::connection('pgsql_external')->select($sql, $params);


        if ($request->get('export') === 'excel') {
            return Excel::download(
                new DetalleAsistenciaExport([
                    'sql' => $sql,
                    'bindings' => $params
                ]),
                'detalle_asistencia.xlsx'
            );
        }


        return response()->json($result);
    }



    public function resumenAsistencia(Request $request)
    {
        // $empresaIds = $request->input('empresa_ids', [2]);
        // if (!is_array($empresaIds)) {
        //     $empresaIds = [$empresaIds];
        // }
        // $empresaIdsStr = implode(',', $empresaIds);

        $departamentoIds = $request->input('departamento_ids', [8]);
        if (!is_array($departamentoIds)) {
            $departamentoIds = [$departamentoIds];
        }


        ds($departamentoIds);



        $departamentoIdsStr = implode(',', $departamentoIds);

        $usuarios = $request->input('usuarios', []);
        $whereUsuarios = '';

        if (!empty($usuarios)) {
            if (!is_array($usuarios)) {
                $usuarios = [$usuarios];
            }
            $ids = implode(',', $usuarios);
            $whereUsuarios = " AND pe.id IN ({$ids}) ";
        }

        $fechaInicio = $request->input('fecha_inicio');

        if (!$fechaInicio) {
            return response()->json([
                'success' => false,
                'message' => 'La fecha_inicio es obligatoria.'
            ], 400);
        }

        $s1_inicio = new \DateTime($fechaInicio);
        $s1_fin = (clone $s1_inicio)->modify('+9 days');

        $s2_inicio = (clone $s1_fin)->modify('+1 day');
        $s2_fin = (clone $s2_inicio)->modify('+6 days');

        $s3_inicio = (clone $s2_fin)->modify('+1 day');
        $s3_fin = (clone $s3_inicio)->modify('+6 days');

        $s4_inicio = (clone $s3_fin)->modify('+1 day');
        $s4_fin = (clone $s4_inicio)->modify('+9 days');

        $S1_IN = $s1_inicio->format('Y-m-d');
        $S1_END = $s1_fin->format('Y-m-d');

        $S2_IN = $s2_inicio->format('Y-m-d');
        $S2_END = $s2_fin->format('Y-m-d');

        $S3_IN = $s3_inicio->format('Y-m-d');
        $S3_END = $s3_fin->format('Y-m-d');

        $S4_IN = $s4_inicio->format('Y-m-d');
        $S4_END = $s4_fin->format('Y-m-d');


        $sql = "
        SELECT
            pe.emp_code AS dni,
            pe.last_name AS apellidos,
            pe.first_name AS nombres,
            pd.dept_name AS departamento,
            pc.company_name AS empresa,

            /* SEMANA 1 */
            (SELECT SUM(ap2.clock_in - ap2.check_in)
             FROM att_payloadbase ap2
             WHERE ap2.emp_id = pe.id
               AND ap2.clock_in >= ap2.check_in
               AND CAST(ap2.clock_in AS date) BETWEEN '{$S1_IN}' AND '{$S1_END}'
            ) AS s1_tardanza,

            (SELECT TO_CHAR(make_interval(secs => SUM(
                        CASE 
                            WHEN ap2.weekday = 5 AND ap2.clock_out > ap2.check_out 
                                THEN ap2.actual_worked + (EXTRACT(EPOCH FROM ap2.clock_out) - EXTRACT(EPOCH FROM ap2.check_out))
                            WHEN ap2.weekday = 5 AND ap2.clock_out < ap2.check_out 
                                THEN ap2.actual_worked
                            WHEN ap2.clock_out > ap2.check_out 
                                THEN ap2.actual_worked + (EXTRACT(EPOCH FROM ap2.clock_out) - EXTRACT(EPOCH FROM ap2.check_out))
                            ELSE ap2.actual_worked + ap2.early_leave 
                        END
                )), 'HH24:MI:SS')
             FROM att_payloadbase ap2
             WHERE ap2.emp_id = pe.id
               AND CAST(ap2.clock_in AS date) BETWEEN '{$S1_IN}' AND '{$S1_END}'
            ) AS s1_trabajadas,


            /* SEMANA 2 */
            (SELECT SUM(ap2.clock_in - ap2.check_in)
             FROM att_payloadbase ap2
             WHERE ap2.emp_id = pe.id
               AND ap2.clock_in >= ap2.check_in
               AND CAST(ap2.clock_in AS date) BETWEEN '{$S2_IN}' AND '{$S2_END}'
            ) AS s2_tardanza,

            (SELECT TO_CHAR(make_interval(secs => SUM(
                        CASE 
                            WHEN ap2.weekday = 5 AND ap2.clock_out > ap2.check_out 
                                THEN ap2.actual_worked + (EXTRACT(EPOCH FROM ap2.clock_out) - EXTRACT(EPOCH FROM ap2.check_out))
                            WHEN ap2.weekday = 5 AND ap2.clock_out < ap2.check_out 
                                THEN ap2.actual_worked
                            WHEN ap2.clock_out > ap2.check_out 
                                THEN ap2.actual_worked + (EXTRACT(EPOCH FROM ap2.clock_out) - EXTRACT(EPOCH FROM ap2.check_out))
                            ELSE ap2.actual_worked + ap2.early_leave 
                        END
                )), 'HH24:MI:SS')
             FROM att_payloadbase ap2
             WHERE ap2.emp_id = pe.id
               AND CAST(ap2.clock_in AS date) BETWEEN '{$S2_IN}' AND '{$S2_END}'
            ) AS s2_trabajadas,


            /* SEMANA 3 */
            (SELECT SUM(ap2.clock_in - ap2.check_in)
             FROM att_payloadbase ap2
             WHERE ap2.emp_id = pe.id
               AND ap2.clock_in >= ap2.check_in
               AND CAST(ap2.clock_in AS date) BETWEEN '{$S3_IN}' AND '{$S3_END}'
            ) AS s3_tardanza,

            (SELECT TO_CHAR(make_interval(secs => SUM(
                        CASE 
                            WHEN ap2.weekday = 5 AND ap2.clock_out > ap2.check_out 
                                THEN ap2.actual_worked + (EXTRACT(EPOCH FROM ap2.clock_out) - EXTRACT(EPOCH FROM ap2.check_out))
                            WHEN ap2.weekday = 5 AND ap2.clock_out < ap2.check_out 
                                THEN ap2.actual_worked
                            WHEN ap2.clock_out > ap2.check_out 
                                THEN ap2.actual_worked + (EXTRACT(EPOCH FROM ap2.clock_out) - EXTRACT(EPOCH FROM ap2.check_out))
                            ELSE ap2.actual_worked + ap2.early_leave 
                        END
                )), 'HH24:MI:SS')
             FROM att_payloadbase ap2
             WHERE ap2.emp_id = pe.id
               AND CAST(ap2.clock_in AS date) BETWEEN '{$S3_IN}' AND '{$S3_END}'
            ) AS s3_trabajadas,


            /* SEMANA 4 */
            (SELECT SUM(ap2.clock_in - ap2.check_in)
             FROM att_payloadbase ap2
             WHERE ap2.emp_id = pe.id
               AND ap2.clock_in >= ap2.check_in
               AND CAST(ap2.clock_in AS date) BETWEEN '{$S4_IN}' AND '{$S4_END}'
            ) AS s4_tardanza,

            (SELECT TO_CHAR(make_interval(secs => SUM(
                        CASE 
                            WHEN ap2.weekday = 5 AND ap2.clock_out > ap2.check_out 
                                THEN ap2.actual_worked + (EXTRACT(EPOCH FROM ap2.clock_out) - EXTRACT(EPOCH FROM ap2.check_out))
                            WHEN ap2.weekday = 5 AND ap2.clock_out < ap2.check_out 
                                THEN ap2.actual_worked
                            WHEN ap2.clock_out > ap2.check_out 
                                THEN ap2.actual_worked + (EXTRACT(EPOCH FROM ap2.clock_out) - EXTRACT(EPOCH FROM ap2.check_out))
                            ELSE ap2.actual_worked + ap2.early_leave 
                        END
                )), 'HH24:MI:SS')
             FROM att_payloadbase ap2
             WHERE ap2.emp_id = pe.id
               AND CAST(ap2.clock_in AS date) BETWEEN '{$S4_IN}' AND '{$S4_END}'
            ) AS s4_trabajadas

        FROM personnel_employee pe
        INNER JOIN personnel_department pd ON pe.department_id = pd.id
        INNER JOIN personnel_company pc ON pd.company_id = pc.id
        WHERE pe.status = 0
        AND pd.id IN ({$departamentoIdsStr})
        {$whereUsuarios}
        GROUP BY pe.id, pe.emp_code, pe.last_name, pe.first_name, pd.dept_name, pc.company_name
        ORDER BY pd.dept_name, pe.last_name;
        ";
        // AND pc.id IN ({$empresaIdsStr})

        $result = DB::connection('pgsql_external')->select($sql);

        if ($request->get('export') === 'excel') {
            return Excel::download(
                new ResumenAsistenciaExport([
                    'sql' => $sql
                ]),
                'resumen_asistencia.xlsx'
            );
        }

        return response()->json([
            'success' => true,
            'semanas' => [
                's1' => [
                    'inicio' => $S1_IN,
                    'fin'    => $S1_END,
                ],
                's2' => [
                    'inicio' => $S2_IN,
                    'fin'    => $S2_END,
                ],
                's3' => [
                    'inicio' => $S3_IN,
                    'fin'    => $S3_END,
                ],
                's4' => [
                    'inicio' => $S4_IN,
                    'fin'    => $S4_END,
                ],
            ],
            'data' => $result
        ]);
    }
}
