<?php

use App\Console\Commands\DailyAttendanceReport;
use App\Console\Commands\Birthday;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command(DailyAttendanceReport::class)
    ->dailyAt('20:00')
    ->timezone(env('APP_TIMEZONE', 'America/Lima'))
    ->onSuccess(function () {
        Log::info('Daily attendance processing completed successfully.');
    })
    ->onFailure(function () {
        Log::error('Daily attendance processing failed.');
    });

Schedule::command(Birthday::class)
    ->dailyAt('08:30')
    ->timezone(env('APP_TIMEZONE', 'America/Lima'))
    ->onSuccess(function () {
        Log::info('Birthday greetings sent successfully.');
    })
    ->onFailure(function () {
        Log::error('Failed to send birthday greetings.');
    });