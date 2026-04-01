<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class EmployeeConceptService implements EmployeeConceptServiceInterface
{
    private const PGSQL_CONNECTION = 'pgsql_external';

    public function storeConcept(
        int $employeeId,
        string $empCode,
        int $conceptId,
        string $startDate,
        string $endDate,
        ?string $comment = null
    ): array {
        $concept = DB::connection(self::PGSQL_CONNECTION)
            ->table('concepts')
            ->where('id', $conceptId)
            ->first();

        if (! $concept) {
            throw new \RuntimeException("El concepto {$conceptId} no existe.");
        }

        return DB::connection(self::PGSQL_CONNECTION)->transaction(function () use (
            $employeeId,
            $empCode,
            $concept,
            $startDate,
            $endDate,
            $comment
        ) {
            $now = now();

            $filters = [
                'employee_id' => $employeeId,
                'emp_code' => $empCode,
                'concept_id' => $concept->id,
                'start_date' => $startDate,
                'end_date' => $endDate,
            ];

            $employeeConceptQuery = DB::connection(self::PGSQL_CONNECTION)
                ->table('employee_concepts')
                ->where($filters);

            $existingEmployeeConcept = $employeeConceptQuery->first();

            $employeeConceptData = [
                'employee_id' => $employeeId,
                'emp_code' => $empCode,
                'concept_id' => $concept->id,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'comment' => $comment,
                'updated_at' => $now,
            ];

            if ($existingEmployeeConcept) {
                $employeeConceptQuery->update($employeeConceptData);
                $employeeConceptId = (int) $existingEmployeeConcept->id;
                $wasUpdated = true;
            } else {
                $employeeConceptId = (int) DB::connection(self::PGSQL_CONNECTION)
                    ->table('employee_concepts')
                    ->insertGetId($employeeConceptData + ['created_at' => $now]);
                $wasUpdated = false;
            }

            $start = Carbon::parse($startDate)->startOfDay();
            $end = Carbon::parse($endDate)->startOfDay();

            for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
                DB::connection(self::PGSQL_CONNECTION)
                    ->table('daily_records')
                    ->updateOrInsert(
                        [
                            'employee_id' => $employeeId,
                            'date' => $date->format('Y-m-d'),
                        ],
                        [
                            'emp_code' => $empCode,
                            'concept_id' => $concept->id,
                            'day_code' => $concept->code,
                            'mobility_eligible' => $concept->affects_mobility,
                            'source' => 'employee_concepts',
                            'notes' => $comment,
                            'processed' => false,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]
                    );
            }

            return [
                'employee_concept_id' => $employeeConceptId,
                'concept_code' => $concept->code,
                'concept_name' => $concept->name,
                'was_updated' => $wasUpdated,
                'total_days_registered' => $start->diffInDays($end) + 1,
            ];
        });
    }
}
