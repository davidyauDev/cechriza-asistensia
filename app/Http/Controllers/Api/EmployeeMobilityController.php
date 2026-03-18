<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EmployeeMobilityController extends Controller
{
    private function mobilityWithEmployeeQuery(?int $year = null, bool $includeEmployeesWithoutMobility = false)
    {
        $connection = DB::connection('pgsql_external');

        if ($includeEmployeesWithoutMobility) {
            if ($year === null) {
                throw new \InvalidArgumentException('Year is required when including employees without mobility.');
            }

            return $connection
                ->table('personnel_employee as pe')
                ->leftJoin('employee_mobility', function ($join) use ($year) {
                    $join->on('pe.id', '=', 'employee_mobility.employee_id')
                        ->where('employee_mobility.year', '=', $year);
                })
                ->where('pe.has_mobility', true)
                ->where('pe.status', '!=', 100)
                ->select(
                    'employee_mobility.id',
                    'pe.id as employee_id',
                    DB::raw((int) $year . ' as year'),
                    'employee_mobility.amount',
                    'employee_mobility.is_active',
                    'employee_mobility.created_at',
                    'pe.emp_code',
                    'pe.first_name',
                    'pe.last_name',
                    DB::raw('CASE WHEN employee_mobility.id IS NULL THEN false ELSE true END as has_mobility'),
                );
        }

        return $connection
            ->table('employee_mobility')
            ->join('personnel_employee as pe', 'pe.id', '=', 'employee_mobility.employee_id')
            ->where('pe.has_mobility', true)
            ->where('pe.status', '!=', 100)
            ->select(
                'employee_mobility.id',
                'employee_mobility.employee_id',
                'employee_mobility.year',
                'employee_mobility.amount',
                'employee_mobility.is_active',
                'employee_mobility.created_at',
                'pe.emp_code',
                'pe.first_name',
                'pe.last_name',
                DB::raw('true as has_mobility'),
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

        $year = $validated['year'] ?? (int) now()->format('Y');
        $query = $this->mobilityWithEmployeeQuery(
            $year,
            true
        );

        $query
            ->orderByRaw('CASE WHEN employee_mobility.id IS NULL THEN 1 ELSE 0 END')
            ->orderBy('pe.last_name')
            ->orderBy('pe.first_name');

        if (isset($validated['employee_id'])) {
            $query->where('pe.id', $validated['employee_id']);
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

    public function set(Request $request)
    {
        $validated = $request->validate([
            'employee_id' => 'required|integer|exists:pgsql_external.personnel_employee,id',
            'year' => 'nullable|integer|min:2000|max:2100',
            'has_mobility' => 'required|boolean',
            'amount' => 'nullable|numeric|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        $employeeId = (int) $validated['employee_id'];
        $year = (int) ($validated['year'] ?? now()->format('Y'));
        $hasMobility = (bool) $validated['has_mobility'];

        return DB::connection('pgsql_external')->transaction(function () use ($employeeId, $year, $hasMobility, $validated) {
            $existing = DB::connection('pgsql_external')
                ->table('employee_mobility')
                ->where('employee_id', $employeeId)
                ->where('year', $year)
                ->first();

            if (! $hasMobility) {
                if ($existing) {
                    DB::connection('pgsql_external')
                        ->table('employee_mobility')
                        ->where('id', $existing->id)
                        ->delete();
                }

                $record = $this->mobilityWithEmployeeQuery($year, true)
                    ->where('pe.id', $employeeId)
                    ->first();

                return response()->json([
                    'success' => true,
                    'data' => $record,
                ]);
            }

            if ($existing) {
                $updates = [];
                if (array_key_exists('amount', $validated) && $validated['amount'] !== null) {
                    $updates['amount'] = $validated['amount'];
                }
                if (array_key_exists('is_active', $validated)) {
                    $updates['is_active'] = (bool) $validated['is_active'];
                }

                if (! empty($updates)) {
                    DB::connection('pgsql_external')
                        ->table('employee_mobility')
                        ->where('id', $existing->id)
                        ->update($updates);
                }
            } else {
                DB::connection('pgsql_external')
                    ->table('employee_mobility')
                    ->insert([
                        'employee_id' => $employeeId,
                        'year' => $year,
                        'amount' => $validated['amount'] ?? 0,
                        'is_active' => array_key_exists('is_active', $validated) ? (bool) $validated['is_active'] : true,
                        'created_at' => now(),
                    ]);
            }

            $record = $this->mobilityWithEmployeeQuery($year, true)
                ->where('pe.id', $employeeId)
                ->first();

            return response()->json([
                'success' => true,
                'data' => $record,
            ]);
        });
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'employee_id' => 'required|integer|exists:pgsql_external.personnel_employee,id',
            'year' => 'required|integer|min:2000|max:2100',
            'amount' => 'required|numeric|min:0',
            'is_active' => 'nullable|boolean',
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
                'is_active' => array_key_exists('is_active', $validated) ? (bool) $validated['is_active'] : true,
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
                'message' => 'ID Invalido.',
            ], 422);
        }

        $id = (int) $id;

        $validated = $request->validate([
            'employee_id' => 'required|integer|exists:pgsql_external.personnel_employee,id',
            'year' => 'required|integer|min:2000|max:2100',
            'amount' => 'required|numeric|min:0',
            'is_active' => 'nullable|boolean',
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
                'is_active' => array_key_exists('is_active', $validated) ? (bool) $validated['is_active'] : ($existing->is_active ?? true),
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
