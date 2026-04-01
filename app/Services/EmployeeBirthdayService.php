<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class EmployeeBirthdayService implements EmployeeBirthdayServiceInterface
{
    private const PGSQL_CONNECTION = 'pgsql_external';

    public function getBirthdaysByMonth(int $month, ?string $search = null): array
    {
        if ($month < 1 || $month > 12) {
            throw new \InvalidArgumentException('El mes debe estar entre 1 y 12.');
        }

        $query = DB::connection(self::PGSQL_CONNECTION)
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
                'pe.birthday',
                'pe.status',
                'pe.department_id',
                'pd.dept_name as department_name',
                'pe.position_id',
                'pp.position_name as position_name',
            ])
            ->where('pe.status', 0)
            ->whereNotNull('pe.birthday')
            ->whereRaw('EXTRACT(MONTH FROM pe.birthday) = ?', [$month])
            ->orderByRaw('EXTRACT(DAY FROM pe.birthday) ASC')
            ->orderBy('pe.last_name')
            ->orderBy('pe.first_name');

        if (! empty($search)) {
            $search = trim($search);
            $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $search).'%';

            $query->where(function ($sub) use ($like) {
                $sub->where('pe.emp_code', 'ilike', $like)
                    ->orWhere('pe.first_name', 'ilike', $like)
                    ->orWhere('pe.last_name', 'ilike', $like)
                    ->orWhere('pe.email', 'ilike', $like)
                    ->orWhere('pd.dept_name', 'ilike', $like);
            });
        }

        $employees = $query->get();

        $monthLabel = Carbon::create()->month($month)->locale('es')->translatedFormat('F');

        $birthdays = [];
        $byDay = [];
        $byDepartment = [];
        $notifications = [];

        foreach ($employees as $employee) {
            $birthday = Carbon::parse($employee->birthday);
            $day = $birthday->format('d');
            $year = $birthday->year;
            $fullName = trim($employee->first_name.' '.$employee->last_name);

            $payload = [
                'id' => $employee->id,
                'employee_id' => $employee->id,
                'dni' => $employee->emp_code,
                'first_name' => $employee->first_name,
                'last_name' => $employee->last_name,
                'full_name' => $fullName,
                'email' => $employee->email,
                'mobile' => $employee->mobile,
                'city' => $employee->city,
                'birthday' => $birthday->toDateString(),
                'birthday_day' => $day,
                'birthday_month' => $month,
                'birthday_year' => $year,
                'department_id' => $employee->department_id,
                'department_name' => $employee->department_name,
                'position_id' => $employee->position_id,
                'position_name' => $employee->position_name,
            ];

            $birthdays[] = $payload;

            $byDay[$day][] = $payload;

            $departmentKey = $employee->department_name ?? 'Sin departamento';
            $byDepartment[$departmentKey][] = $payload;

            $notifications[] = [
                'id' => $employee->id,
                'employee_id' => $employee->id,
                'dni' => $employee->emp_code,
                'title' => 'Cumpleaños del mes',
                'message' => "{$fullName} cumple el {$day} de {$monthLabel}.",
                'selected' => true,
                'type' => 'birthday',
                'birthday' => $birthday->toDateString(),
                'birthday_day' => $day,
                'birthday_month' => $month,
                'birthday_year' => $year,
                'department_name' => $employee->department_name,
                'position_name' => $employee->position_name,
            ];
        }

        return [
            'success' => true,
            'month' => $month,
            'month_label' => ucfirst($monthLabel),
            'total_birthdays' => count($birthdays),
            'birthdays' => $birthdays,
            'by_day' => $byDay,
            'by_department' => $byDepartment,
            'notifications' => $notifications,
            'selected_users' => $notifications,
        ];
    }
}
