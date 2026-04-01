<?php

namespace App\Providers;

use App\Repositories\AttendanceServiceRepository;
use App\Repositories\AttendanceServiceRepositoryInterface;
use App\Repositories\DbTechnicianRepository;
use App\Repositories\EloquentUserRepository;
use App\Repositories\EventoServiceRepository;
use App\Repositories\EventoServiceRepositoryInterface;
use App\Repositories\TechnicianRepositoryInterface;
use App\Repositories\UserRepositoryInterface;
use App\Services\AttendanceService;
use App\Services\AttendanceServiceInterface;
use App\Services\AuthService;
use App\Services\AuthServiceInterface;
use App\Services\EmployeeConceptService;
use App\Services\EmployeeConceptServiceInterface;
use App\Services\EventoService;
use App\Services\EventoServiceInterface;
use App\Services\TechnicianService;
use App\Services\TechnicianServiceInterface;
use App\Services\UserService;
use App\Services\UserServiceInterface;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Bind repository interfaces to implementations
        $this->app->bind(AttendanceServiceRepositoryInterface::class, AttendanceServiceRepository::class);
        $this->app->bind(UserRepositoryInterface::class, EloquentUserRepository::class);
        $this->app->bind(TechnicianRepositoryInterface::class, DbTechnicianRepository::class);
        $this->app->bind(EventoServiceRepositoryInterface::class, EventoServiceRepository::class);

        // Bind service interfaces
        $this->app->bind(AuthServiceInterface::class, AuthService::class);
        $this->app->bind(EmployeeConceptServiceInterface::class, EmployeeConceptService::class);
        $this->app->bind(UserServiceInterface::class, UserService::class);
        $this->app->bind(AttendanceServiceInterface::class, AttendanceService::class);
        $this->app->bind(TechnicianServiceInterface::class, TechnicianService::class);
        $this->app->bind(EventoServiceInterface::class, EventoService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
