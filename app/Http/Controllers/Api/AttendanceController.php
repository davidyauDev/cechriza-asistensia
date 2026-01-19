<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateAttendanceRequest;
use App\Models\Attendance;
use App\Http\Requests\AttendanceIndexRequest;
use App\Http\Requests\StoreAttendanceRequest;
use App\Http\Resources\AttendanceResource;
use App\Services\AttendanceServiceInterface;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;
use App\Models\User;


class AttendanceController extends Controller
{
    use ApiResponseTrait;
    public function __construct(
        private AttendanceServiceInterface $attendanceService
    ) {
        //
    }
    public function index(AttendanceIndexRequest $request)
    {
        return $this->successResponse(
            $this->attendanceService->index($request),
            'Attendance records retrieved successfully'
        );
    }

    public function forUser(AttendanceIndexRequest $request)
    {
        return $this->successResponse(
            $this->attendanceService->forUser($request),
            'User attendance records retrieved successfully'
        );
    }

    /**
     * Obtener estadísticas de asistencia por usuario
     */
    public function userStats(Request $request, User $user)
    {
        $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'month' => 'nullable|integer|min:1|max:12',
            'year' => 'nullable|integer|min:2020',
        ]);

        return $this->successResponse(
            $this->attendanceService->statsByUser($request->all(), $user),
            'User attendance statistics retrieved successfully'
        );
    }

    


    public function store(StoreAttendanceRequest $request)
    {
        $data = $request->validated();
        return $this->successResponse(
            $this->attendanceService->store($request),
            'Attendance record created successfully'
        );
    }

   

    public function show(Attendance $attendance)
    {
        $attendance->loadMissing(['user', 'image']);
        return $this->successResponse(
            new AttendanceResource($attendance),
            'Attendance record retrieved successfully'
        );
    }

    public function update(UpdateAttendanceRequest $request, Attendance $attendance)
    {

        // ds($request->validated());
            // $validated = $request->validate([
            //     'notes' => ['nullable', 'string', 'max:255'],
            //     'device_model' => ['nullable', 'string', 'max:255'],
            //     'battery_percentage' => ['nullable', 'integer', 'min:0', 'max:100'],
            //     'signal_strength' => ['nullable', 'integer', 'min:0', 'max:4'],
            //     'network_type' => ['nullable', 'string', 'max:50'],
            // ]);
        // $attendance->update($request->validated());
        $request->validated();

     
        return $this->successResponse(
            $this->attendanceService->update($attendance, $request),
            'Attendance record updated successfully'
        );
    }

    public function destroy(Attendance $attendance)
    {
        return $this->successResponse(
            $this->attendanceService->delete($attendance),
            204
        );
    }
}
