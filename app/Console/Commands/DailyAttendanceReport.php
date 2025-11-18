<?php

namespace App\Console\Commands;

use App\Jobs\SendMissingCheckoutMailJob;
use App\Models\User;
use Illuminate\Console\Command;
class DailyAttendanceReport extends Command
{

    protected $signature = 'attendance:process-daily-checks';

    protected $description = 'Process daily attendance checks';

    private function getUsersNotCheckedOut()
    {
        // Lógica para obtener usuarios que no han registrado su salida
        $users = User::whereDoesntHave('attendances', function ($query) {
            // $query->whereDate('created_at', date('Y-m-d'));
            $query->where('type', 'check_out')->whereDate('created_at', date('Y-m-d'));
        })->get();

        return $users;
    }

    public function handle()
    {
        $users = $this->getUsersNotCheckedOut();

      
        foreach ($users as $user) {
            SendMissingCheckoutMailJob::dispatch($user);
        }

        // Lógica para procesar los registros de asistencia diaria
        $this->info('Daily attendance processing completed successfully.');
        return self::SUCCESS;
    }



}