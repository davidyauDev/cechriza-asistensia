<?php

namespace App\Console\Commands;

use App\Jobs\SendBirthdayGreetingJob;
use DB;
use Illuminate\Console\Command;

class Birthday extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'birthday:send-greetings';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send birthday greetings to users';

    /**
     * Execute the console command.
     *
     * @return int
     */

    private function getUsersWithBirthdayToday(): array 
    {
        // Lógica para obtener usuarios que cumplen años hoy
        // $users = User::whereRaw('DATE(birthdate) = CURDATE()')->get();
        // $date = '2005-01-08';

        $date = date('Y-m-d');

        $where = "TO_CHAR(birthday, 'MM-DD') = TO_CHAR(?::date, 'MM-DD') AND is_active = true";

        $users = DB::connection('pgsql_external')->table('personnel_employee')
            ->whereRaw($where, [$date])
            ->get();

            ds($users);

        return $users->toArray();
    }

    public function handle()
    {

        $users = $this->getUsersWithBirthdayToday();

        foreach ($users as $user) {
            
            // Dispatch job to send birthday greeting email
        SendBirthdayGreetingJob::dispatch($user); 
        }

        // Lógica para enviar saludos de cumpleaños a los usuarios
        $this->info('Birthday greetings sent successfully.');
        return self::SUCCESS;
    }

}