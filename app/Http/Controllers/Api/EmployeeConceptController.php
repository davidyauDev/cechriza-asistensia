<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Exports\MovilidadMensualExport;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class EmployeeConceptController extends Controller
{
    public function storeConcept(Request $request)
    {
        $request->validate([
            'employee_id' => 'required|integer',
            'emp_code' => 'required|string', 
            'concept_id' => 'required|integer',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'comment' => 'nullable|string',
        ]);

        DB::beginTransaction();

        try {

            $concept = DB::connection('pgsql_external')
                ->table('concepts')
                ->where('id', $request->concept_id)
                ->first();

            if (!$concept) {
                return response()->json(['error' => 'Concepto no existe'], 404);
            }

            $employeeConceptId = DB::connection('pgsql_external')
                ->table('employee_concepts')
                ->insertGetId([
                    'employee_id' => $request->employee_id,
                    'emp_code' => $request->emp_code,  // DNI
                    'concept_id' => $concept->id,
                    'start_date' => $request->start_date,
                    'end_date' => $request->end_date,
                    'comment' => $request->comment,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

            $start = Carbon::parse($request->start_date);
            $end = Carbon::parse($request->end_date);

            for ($date = $start; $date->lte($end); $date->addDay()) {

                DB::connection('pgsql_external')
                    ->table('daily_records')
                    ->updateOrInsert(
                        [
                            'employee_id' => $request->employee_id,
                            'date' => $date->format('Y-m-d'),
                        ],
                        [
                            'emp_code' => $request->emp_code,
                            'concept_id' => $concept->id,
                            'day_code' => $concept->code,
                            'mobility_eligible' => $concept->affects_mobility,
                            'source' => 'employee_concepts',
                            'notes' => $request->comment,
                            'processed' => false,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]
                    );
            }

            DB::commit();

            return response()->json([
                'message' => 'Concepto registrado correctamente.',
                'employee_concept_id' => $employeeConceptId,
                'concept_code' => $concept->code,
                'total_days_registered' => $start->diffInDays($end) + 1
            ], 201);
        } catch (\Exception $e) {

            DB::rollBack();

            return response()->json([
                'error' => 'Error al registrar concepto',
                'details' => $e->getMessage(),
            ], 500);
        }
    }

    public function monthlyMobilityReport(Request $request)
    {
        $request->validate([
            'year' => 'required|integer',
            'month' => 'required|integer|min:1|max:12',
            'descargar' => 'nullable|boolean',
        ]);

        $year = $request->year;
        $month = str_pad($request->month, 2, '0', STR_PAD_LEFT);

        $startDate = Carbon::parse("$year-$month-01")->startOfMonth();
        $endDate = Carbon::parse("$year-$month-01")->endOfMonth();

        $employees = DB::connection('pgsql_external')
            ->table('personnel_employee')
            ->join(
                'personnel_position',
                'personnel_employee.position_id',
                '=',
                'personnel_position.id'
            )
            ->join(
                'personnel_department',
                'personnel_employee.department_id',
                '=',
                'personnel_department.id'
            )
            ->where('personnel_employee.position_id', 7)
            ->where('personnel_employee.status', '!=', 100)
            ->select(
                'personnel_employee.id',
                'personnel_employee.emp_code',
                'personnel_employee.first_name',
                'personnel_employee.last_name',
                'personnel_employee.position_id',
                'personnel_employee.create_time',
                'personnel_employee.city',
                'personnel_position.position_name as position_name',
                'personnel_department.dept_name as department_name'
            )
            ->get();


        $records = DB::connection('pgsql_external')
            ->table('daily_records')
            ->whereBetween('date', [$startDate, $endDate])
            ->get()
            ->groupBy('emp_code');

        // Obtener montos base de movilidad para el año actual
        $mobilityBase = DB::connection('pgsql_external')
            ->table('employee_mobility')
            ->where('year', $year)
            ->get()
            ->keyBy('employee_id');

        $result = [];

        foreach ($employees as $emp) {

            $dni = $emp->emp_code;


            $days = $records->get($dni, collect());

            $vac = $days->where('day_code', 'V')->count();
            $dm = $days->where('day_code', 'DM')->count();
            $nm = $days->where('day_code', 'NM')->count();
            $as = $days->where('day_code', '1')->count();
            $sr = $days->where('day_code', 'SR')->count();


            $mobilityDays = $days->where('mobility_eligible', true)->count();

            // Obtener el monto base de movilidad del empleado
            $employeeMobility = $mobilityBase->get($emp->id);
            $mobilityAmount = $employeeMobility ? (float) $employeeMobility->amount : 5;
            $totalPay = (($mobilityAmount/30) * ($as + $sr)) - 75;

            $dailyData = $days->mapWithKeys(function ($d) {
                return [
                    $d->date => [
                        'code' => $d->day_code,
                        'mobility_counted' => (bool) $d->mobility_eligible,
                    ]
                ];
            })->toArray();
            $result[] = array_merge(
                [
                    'employee' => [
                        'id' => $emp->id,
                        'dni' => $dni,
                        'first_name' => $emp->first_name,
                        'last_name' => $emp->last_name,
                        'position_id' => $emp->position_id,
                        'position_name' => $emp->position_name,
                        'department_name' => $emp->department_name,
                        'create_time' => $emp->create_time ? Carbon::parse($emp->create_time)->format('Y-m-d') : null,
                        'city' => $emp->city,
                    ],
                    'summary' => [
                        'total_days' => $as + $sr,
                        'vacation_days' => $vac,
                        'medical_leave_days' => $dm,
                        'no_mark_days' => $nm,
                        'days_with_mobility' => $mobilityDays,
                        'mobility_amount' => $mobilityAmount,
                        'total_mobility_to_pay' => $totalPay,
                    ],
                ],
                $dailyData
            );
        }

        // Si se solicita descargar, generar archivo Excel
        if ($request->descargar) {
            // Obtener todos los días del mes para las columnas
            $diasDelMes = [];
            $ultimoDia = Carbon::create($year, $request->month, 1)->endOfMonth()->day;
            
            for ($dia = 1; $dia <= $ultimoDia; $dia++) {
                $fecha = Carbon::create($year, $request->month, $dia);
                $key = $fecha->locale('es')->translatedFormat('j-M');
                $key = preg_replace_callback(
                    '/-(\p{L}+)/u',
                    fn($m) => '-' . ucfirst($m[1]),
                    str_replace('.', '', $key)
                );
                $diasDelMes[] = $key;
            }

            $nombreMes = Carbon::create($year, $request->month, 1)
                ->locale('es')
                ->translatedFormat('F');
            
            $nombreArchivo = "Movilidad_{$nombreMes}_{$year}.xlsx";

            return Excel::download(
                new MovilidadMensualExport($result, $diasDelMes),
                $nombreArchivo
            );
        }

        return response()->json($result);
    }
}
