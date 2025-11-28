<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReporteAsistenciaController extends Controller
{

    public function detalleAsist(Request $request)
    {
        $fechaInicio = $request->input('fecha_inicio', '2025-11-01');
        $fechaFin = $request->input('fecha_fin', '2025-11-25');
        $empresaIds = $request->input('empresa_ids', [2]);
        $departamentoIds = $request->input('departamento_ids', [8]);

        // Convertir arrays PHP a arrays PostgreSQL
        $empresaIdsPG = '{' . implode(',', $empresaIds) . '}';
        $departamentoIdsPG = '{' . implode(',', $departamentoIds) . '}';

        $result = DB::connection('pgsql_external')->select(<<<SQL
        select
            pe.emp_code as dni,
            pe.last_name as apellidos,
            pe.first_name as nombres,
            pd.dept_name as departamento,
            pc.company_name as empresa,
            ap.att_date as fecha,
            cast(ap.check_in as time) as h_ingreso,
            cast(ap.check_out as time) as h_salida,
            cast(ap.clock_in as time) as m_ingreso,
            cast(ap.clock_out as time) as m_salida,
            TO_CHAR(make_interval(secs => nullif(ap.late, 0)), 'HH24:MI:SS') as tardanza,
            TO_CHAR(make_interval(secs => case
                when ap.weekday = 5 and ap.clock_out > ap.check_out then ap.actual_worked + (extract(EPOCH from ap.clock_out) - extract(EPOCH from ap.check_out))
                when ap.weekday = 5 and ap.clock_out < ap.check_out then ap.actual_worked
                when ap.clock_out > ap.check_out then ap.actual_worked + (extract(EPOCH from ap.clock_out) - extract(EPOCH from ap.check_out))
                else ap.actual_worked + ap.early_leave end), 'HH24:MI:SS') as total_trabajado
        from
            personnel_employee pe
        inner join att_payloadbase ap on pe.id = ap.emp_id
        inner join personnel_department pd on pe.department_id = pd.id
        inner join personnel_company pc on pd.company_id = pc.id
        where
            pe.status = 0
            and pc.id = any(?)
            and pd.id = any(?)
            and cast(ap.clock_in as date) between ? and ?
        order by
            pd.dept_name,
            pe.last_name,
            ap.att_date
    SQL, [
            $empresaIdsPG,
            $departamentoIdsPG,
            $fechaInicio,
            $fechaFin
        ]);

        return response()->json($result);
    }


    public function resumenAsistencia(Request $request)
    {
        $empresaId = $request->input('empresa_id', 2);
        $departamentoId = $request->input('departamento_id', 8);
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
          AND pc.id = {$empresaId}
          AND pd.id = {$departamentoId}
        GROUP BY pe.id, pe.emp_code, pe.last_name, pe.first_name, pd.dept_name, pc.company_name
        ORDER BY pd.dept_name, pe.last_name;
    ";

        $result = DB::connection('pgsql_external')->select($sql);

        return response()->json([
            'success' => true,
            'data' => $result
        ]);
    }
}
