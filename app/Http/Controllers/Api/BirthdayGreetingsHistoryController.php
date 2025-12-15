<?php

namespace App\Http\Controllers\Api;

use App\Jobs\SendBirthdayGreetingJob;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;

class BirthdayGreetingsHistoryController extends Controller
{
    public function index()
    {
        $result = DB::connection('pgsql_external')->table('birthday_greetings_history')->join(
            'personnel_employee as employees',
            'birthday_greetings_history.employee_id',
            '=',
            'employees.id'
        )->select(
                'birthday_greetings_history.id',
                'birthday_greetings_history.employee_id',
                'employees.first_name',
                'employees.last_name',
                'employees.birthday',
                'employees.email',
                'birthday_greetings_history.sent_at',
                'birthday_greetings_history.status',
                'birthday_greetings_history.error_message',
            )->orderBy('birthday_greetings_history.sent_at', 'desc')->get();

        return response()->json($result);
    }

    public function retryFailedGreetings()
    {
        $id = request()->input('id');

        $failedGreeting = DB::connection('pgsql_external')
            ->table('birthday_greetings_history')
            ->where('birthday_greetings_history.id', $id)
            ->where('birthday_greetings_history.status', 'failed')
            ->join(
                'personnel_employee as employees',
                'birthday_greetings_history.employee_id',
                '=',
                'employees.id'
            )->select(
                'birthday_greetings_history.id as greeting_id',
                'birthday_greetings_history.employee_id as id',
                'employees.first_name',
                'employees.last_name',
                'employees.email',
            )
            ->first();

        if (!$failedGreeting) {
            return response()->json(['message' => 'No se encontró un saludo fallido con el ID proporcionado.'], 404);
        }

        try {
            SendBirthdayGreetingJob::dispatch($failedGreeting);

            DB::connection('pgsql_external')
                ->table('birthday_greetings_history')
                ->where('birthday_greetings_history.id', $id)
                ->update(['status' => 'sent', 'sent_at' => now(), 'error_message' => null]);

            return response()->json(['message' => 'Saludo de cumpleaños reenviado con éxito.']);

        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al reintentar el saludo: ' . $e->getMessage()], 500);
        }


    }
}
