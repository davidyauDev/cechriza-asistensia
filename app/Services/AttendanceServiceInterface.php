<?php

namespace App\Services;

use App\Http\Requests\AttendanceIndexRequest;
use App\Http\Requests\StoreAttendanceRequest;
use App\Http\Requests\UpdateAttendanceRequest;
use App\Http\Resources\AttendanceResource;
use App\Models\Attendance;
use App\Models\User;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;



interface AttendanceServiceInterface
{
    public function index(AttendanceIndexRequest $request): AnonymousResourceCollection;
    public function forUser(AttendanceIndexRequest $request, int $userId): AnonymousResourceCollection;

    public function statsByUser(array $request, User $user): array;

    public function store(StoreAttendanceRequest $request): AttendanceResource;

    public function update(Attendance $attendance, UpdateAttendanceRequest $data): AttendanceResource;
    public function delete(Attendance $attendance): void;
}