<?php

namespace App\Repositories;

use App\Http\Requests\AttendanceIndexRequest;
use App\Http\Requests\StoreAttendanceRequest;
use App\Models\Attendance;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
interface AttendanceServiceRepositoryInterface
        {
            public function getFilteredAttendances(AttendanceIndexRequest $request): LengthAwarePaginator;
            public function getFilteredAttendancesForUser(AttendanceIndexRequest $request, int $userId): LengthAwarePaginator;

            public function statsByUser(array $request, int $userId): array;

            public function createAttendance(StoreAttendanceRequest $data): Attendance;

}