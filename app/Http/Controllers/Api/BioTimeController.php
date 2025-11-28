<?php

namespace App\Http\Controllers\Api;

use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class BioTimeController extends Controller
{
    public function empresas()
    {
        $result = DB::connection('pgsql_external')->select("
            SELECT id, company_code
            FROM personnel_company
            ORDER BY company_code
        ");

        return response()->json([
            'success' => true,
            'data' => $result
        ]);
    }

    public function departamentos()
    {
        $result = DB::connection('pgsql_external')->select("
            SELECT id, dept_name 
            FROM personnel_department
            ORDER BY dept_name
        ");

        return response()->json([
            'success' => true,
            'data' => $result
        ]);
    }

    public function empleadosPorDepartamento(Request $request)
    {
        $departamentoId = $request->input('department_id');
        $query = "
        SELECT id, emp_code, first_name, last_name
        FROM personnel_employee
    ";

        $params = [];

        if ($departamentoId) {
            $query .= " WHERE department_id = ? ";
            $params[] = $departamentoId;
        }
        $query .= " ORDER BY last_name, first_name ";
        $result = DB::connection('pgsql_external')->select($query, $params);

        return response()->json([
            'success' => true,
            'data' => $result
        ]);
    }
}
