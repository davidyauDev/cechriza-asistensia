<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\EmployeeBirthdayServiceInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PersonnelEmployeeController extends Controller
{
    public function __construct(
        private readonly EmployeeBirthdayServiceInterface $birthdayService
    ) {}

    private function baseQuery()
    {
        return DB::connection('pgsql_external')
            ->table('personnel_employee as pe')
            ->leftJoin('personnel_department as pd', 'pe.department_id', '=', 'pd.id')
            ->leftJoin('personnel_position as pp', 'pe.position_id', '=', 'pp.id')
            ->select([
                'pe.id',
                'pe.emp_code',
                'pe.first_name',
                'pe.last_name',
                'pe.email',
                'pe.mobile',
                'pe.city',
                'pe.status',
                'pe.is_active',
                'pe.has_mobility',
                'pe.department_id',
                'pe.position_id',
                'pe.create_time',
                'pe.change_time',
                'pe.hire_date',
                'pd.dept_name as department_name',
                'pp.position_name as position_name',
            ]);
    }

    public function index(Request $request)
    {
        $validated = $request->validate([
            'q' => 'nullable|string|max:150',
            'department_id' => 'nullable|integer',
            'position_id' => 'nullable|integer',
            'status' => 'nullable|integer',
            'is_active' => 'nullable|boolean',
            'has_mobility' => 'nullable|boolean',
            'per_page' => 'nullable|integer|min:1|max:500',
            'paginate' => 'nullable|boolean',
        ]);

        $query = $this->baseQuery()
            ->where('pe.deleted', false)
            ->orderBy('pe.last_name')
            ->orderBy('pe.first_name');

        if (! empty($validated['q'] ?? null)) {
            $q = trim((string) $validated['q']);
            $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $q).'%';
            $query->where(function ($sub) use ($like) {
                $sub
                    ->where('pe.emp_code', 'ilike', $like)
                    ->orWhere('pe.first_name', 'ilike', $like)
                    ->orWhere('pe.last_name', 'ilike', $like)
                    ->orWhere('pe.email', 'ilike', $like)
                    ->orWhere('pe.mobile', 'ilike', $like);
            });
        }

        if (isset($validated['department_id'])) {
            $query->where('pe.department_id', $validated['department_id']);
        }

        if (isset($validated['position_id'])) {
            $query->where('pe.position_id', $validated['position_id']);
        }

        if (isset($validated['status'])) {
            $query->where('pe.status', $validated['status']);
        }

        if (array_key_exists('is_active', $validated)) {
            $query->where('pe.is_active', (bool) $validated['is_active']);
        }

        if (array_key_exists('has_mobility', $validated)) {
            $query->where('pe.has_mobility', (bool) $validated['has_mobility']);
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

    public function show(string $id)
    {
        if (! ctype_digit($id) || (int) $id < 1) {
            return response()->json([
                'message' => 'ID Invalido.',
            ], 422);
        }

        $record = $this->baseQuery()
            ->where('pe.id', (int) $id)
            ->first();

        if (! $record) {
            return response()->json([
                'message' => 'Empleado no encontrado.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $record,
        ]);
    }

    public function update(Request $request, string $id)
    {
        if (! ctype_digit($id) || (int) $id < 1) {
            return response()->json([
                'message' => 'ID Invalido.',
            ], 422);
        }

        $validated = $request->validate([
            'emp_code' => 'nullable|string|max:50',
            'first_name' => 'nullable|string|max:120',
            'last_name' => 'nullable|string|max:120',
            'email' => 'nullable|email|max:255',
            'mobile' => 'nullable|string|max:30',
            'city' => 'nullable|string|max:120',
            'department_id' => 'nullable|integer|exists:pgsql_external.personnel_department,id',
            'position_id' => 'nullable|integer|exists:pgsql_external.personnel_position,id',
            'status' => 'nullable|integer',
            'is_active' => 'nullable|boolean',
            'has_mobility' => 'nullable|boolean',
        ]);

        $employeeId = (int) $id;

        $existing = DB::connection('pgsql_external')
            ->table('personnel_employee')
            ->where('id', $employeeId)
            ->first();

        if (! $existing) {
            return response()->json([
                'message' => 'Empleado no encontrado.',
            ], 404);
        }

        $updates = [];
        foreach (['emp_code', 'first_name', 'last_name', 'email', 'mobile', 'city', 'department_id', 'position_id', 'status'] as $field) {
            if (array_key_exists($field, $validated)) {
                $updates[$field] = $validated[$field];
            }
        }

        if (array_key_exists('is_active', $validated)) {
            $updates['is_active'] = (bool) $validated['is_active'];
        }

        if (array_key_exists('has_mobility', $validated)) {
            $updates['has_mobility'] = (bool) $validated['has_mobility'];
        }

        if (empty($updates)) {
            return response()->json([
                'success' => true,
                'data' => $this->baseQuery()->where('pe.id', $employeeId)->first(),
            ]);
        }

        $updates['change_time'] = now();
        $updates['update_time'] = now();

        DB::connection('pgsql_external')
            ->table('personnel_employee')
            ->where('id', $employeeId)
            ->update($updates);

        $record = $this->baseQuery()
            ->where('pe.id', $employeeId)
            ->first();

        return response()->json([
            'success' => true,
            'data' => $record,
        ]);
    }

    public function birthdaysByMonth(Request $request)
    {
        $validated = $request->validate([
            'month' => 'required|integer|min:1|max:12',
            'search' => 'nullable|string|max:150',
        ]);

        try {
            return response()->json(
                $this->birthdayService->getBirthdaysByMonth(
                    (int) $validated['month'],
                    $validated['search'] ?? null
                )
            );
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al consultar los cumpleaños del mes.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
