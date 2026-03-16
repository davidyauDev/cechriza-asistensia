<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EmployeeMobilityController extends Controller
{
    private function mobilityWithEmployeeQuery()
    {
        return DB::connection('pgsql_external')
            ->table('employee_mobility')
            ->join('personnel_employee as pe', 'pe.id', '=', 'employee_mobility.employee_id')
            ->select(
                'employee_mobility.id',
                'employee_mobility.employee_id',
                'employee_mobility.year',
                'employee_mobility.amount',
                'employee_mobility.created_at',
                'pe.emp_code',
                'pe.first_name',
                'pe.last_name',
            );
    }

    public function index(Request $request)
    {
        $validated = $request->validate([
            'employee_id' => 'nullable|integer',
            'year' => 'nullable|integer|min:2000|max:2100',
            'per_page' => 'nullable|integer|min:1|max:500',
            'paginate' => 'nullable|boolean',
        ]);

        $query = $this->mobilityWithEmployeeQuery()
            ->orderByDesc('employee_mobility.year')
            ->orderBy('pe.last_name')
            ->orderBy('pe.first_name');

        if (isset($validated['employee_id'])) {
            $query->where('employee_mobility.employee_id', $validated['employee_id']);
        }

        if (isset($validated['year'])) {
            $query->where('employee_mobility.year', $validated['year']);
        }

        $paginate = array_key_exists('paginate', $validated) ? (bool) $validated['paginate'] : true;
        if (! $paginate) {
            return response()->json([
                'success' => true,
                'data' => $query->get(),
            ]);
        }

        $perPage = $validated['per_page'] ?? 50;

        return response()->json([
            'success' => true,
            'data' => $query->paginate($perPage),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'employee_id' => 'required|integer|exists:pgsql_external.personnel_employee,id',
            'year' => 'required|integer|min:2000|max:2100',
            'amount' => 'required|numeric|min:0',
        ]);

        $alreadyExists = DB::connection('pgsql_external')
            ->table('employee_mobility')
            ->where('employee_id', $validated['employee_id'])
            ->where('year', $validated['year'])
            ->exists();

        if ($alreadyExists) {
            return response()->json([
                'message' => 'Ya existe un registro de movilidad para ese empleado y año.',
            ], 422);
        }

        $id = DB::connection('pgsql_external')
            ->table('employee_mobility')
            ->insertGetId([
                'employee_id' => $validated['employee_id'],
                'year' => $validated['year'],
                'amount' => $validated['amount'],
                'created_at' => now(),
            ]);

        $record = $this->mobilityWithEmployeeQuery()
            ->where('employee_mobility.id', $id)
            ->first();

        return response()->json([
            'success' => true,
            'data' => $record,
        ], 201);
    }

    public function update(Request $request, string $id)
    {
        if (! ctype_digit($id) || (int) $id < 1) {
            return response()->json([
                'message' => 'ID invÃ¡lido.',
            ], 422);
        }

        $id = (int) $id;

        $validated = $request->validate([
            'employee_id' => 'required|integer|exists:pgsql_external.personnel_employee,id',
            'year' => 'required|integer|min:2000|max:2100',
            'amount' => 'required|numeric|min:0',
        ]);

        $existing = DB::connection('pgsql_external')
            ->table('employee_mobility')
            ->where('id', $id)
            ->first();

        if (! $existing) {
            return response()->json([
                'message' => 'Registro de movilidad no encontrado.',
            ], 404);
        }

        $duplicate = DB::connection('pgsql_external')
            ->table('employee_mobility')
            ->where('employee_id', $validated['employee_id'])
            ->where('year', $validated['year'])
            ->where('id', '!=', $id)
            ->exists();

        if ($duplicate) {
            return response()->json([
                'message' => 'Ya existe otro registro de movilidad para ese empleado y año.',
            ], 422);
        }

        DB::connection('pgsql_external')
            ->table('employee_mobility')
            ->where('id', $id)
            ->update([
                'employee_id' => $validated['employee_id'],
                'year' => $validated['year'],
                'amount' => $validated['amount'],
            ]);

        $record = $this->mobilityWithEmployeeQuery()
            ->where('employee_mobility.id', $id)
            ->first();

        return response()->json([
            'success' => true,
            'data' => $record,
        ]);
    }
}
