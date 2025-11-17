<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
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

    public function forUser(AttendanceIndexRequest $request, User $user)
    {
        return $this->successResponse(
            $this->attendanceService->forUser($request, $user->id),
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

    // public function store(StoreAttendanceRequest $request)
    // {
    //     $data = $request->validated();

    //     $existing = Attendance::with(['user', 'image'])
    //         ->where('user_id', $data['user_id'])
    //         ->where('client_id', $data['client_id'])
    //         ->first();

    //     if ($existing) {
    //         return new AttendanceResource($existing);
    //     }

    //     DB::beginTransaction();

    //     try {
    //         $attendance = Attendance::create(collect($data)->except('photo')->toArray());

    //         $imageUrl = null;
    //         if ($request->hasFile('photo')) {
    //             $disk = 'public'; // Usar disco público para acceso web
    //             $path = $request->file('photo')->store('attendance_photos', $disk);
    //             $attendance->image()->create(['path' => $path]);

    //             // Generar la URL completa de la imagen usando el helper
    //             $imageUrl = ImageHelper::getFullImageUrl($path);
    //         }

    //         $punchTime = Carbon::createFromTimestampMs($data['timestamp'], 'America/Lima')
    //             ->format('Y-m-d H:i:s.v O');

    //         DB::connection('pgsql_external')->table('iclock_transaction')->insert([
    //             'emp_code' => $data['emp_code'],
    //             'punch_time' => $punchTime,
    //             'punch_state' => $data['type'] === 'check_in' ? 0 : 1,
    //             'verify_type' => 101,
    //             'terminal_sn' => 'App',
    //             'latitude' => $data['latitude'],
    //             'longitude' => $data['longitude'],
    //             'gps_location' => $data['address'] ?? null,
    //             'mobile' => 2,
    //             'source' => 3,
    //             'purpose' => 1,
    //             'is_attendance' => true,
    //             'upload_time' => now(),
    //             'sync_status' => 0,
    //             'emp_id' => $data['user_id'],
    //             'is_mask' => 255,
    //             'temperature' => 255,
    //             'identificador' => $data['client_id'] ?? (string) Str::uuid(),
    //             'imagen_url' => $imageUrl,
    //         ]);

    //         DB::commit();

    //         $attendance->loadMissing(['user:id,name', 'image:id,attendance_id,path']);

    //         return (new AttendanceResource($attendance))
    //             ->response()
    //             ->setStatusCode(201);

    //     } catch (Throwable $e) {
    //         DB::rollBack();

    //         Log::error('Error creando asistencia', [
    //             'error' => $e->getMessage(),
    //             'trace' => $e->getTraceAsString(),
    //         ]);

    //         return response()->json([
    //             'message' => 'Error al registrar asistencia: ' . $e->getMessage(),
    //         ], 500);
    //     }
    // }


    public function store(StoreAttendanceRequest $request)
    {
        $data = $request->validated();
        ds($data);
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

    public function update(Request $request, Attendance $attendance)
    {
        $validated = $request->validate([
            'notes' => ['nullable', 'string', 'max:255'],
            'device_model' => ['nullable', 'string', 'max:255'],
            'battery_percentage' => ['nullable', 'integer', 'min:0', 'max:100'],
            'signal_strength' => ['nullable', 'integer', 'min:0', 'max:4'],
            'network_type' => ['nullable', 'string', 'max:50'],
        ]);
        $attendance->update($validated);
        return $this->successResponse(
            new AttendanceResource($attendance->load(['user', 'image'])),
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
