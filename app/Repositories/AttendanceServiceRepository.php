<?php

namespace App\Repositories;

use App\Helpers\ImageHelper;
use App\Http\Requests\AttendanceIndexRequest;
use App\Http\Requests\StoreAttendanceRequest;
use App\Http\Requests\UpdateAttendanceRequest;
use App\Models\Attendance;
use App\Repositories\AttendanceServiceRepositoryInterface;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Str;
use Symfony\Component\CssSelector\Exception\InternalErrorException;

class AttendanceServiceRepository implements AttendanceServiceRepositoryInterface
{


    public function getFilteredAttendances(AttendanceIndexRequest $filters): LengthAwarePaginator
    {
        $query = Attendance::with(['user:id,name,emp_code', 'image:id,attendance_id,path']);

        if (!empty($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        if (!empty($filters['start_date'])) {
            $startMs = Carbon::parse($filters['start_date'])->timestamp * 1000;
            $query->where('timestamp', '>=', $startMs);
        }
        if (!empty($filters['end_date'])) {
            $endMs = Carbon::parse($filters['end_date'])->timestamp * 1000;
            $query->where('timestamp', '<=', $endMs);
        }


        if (!empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (!empty($filters['search'])) {
            $query->whereHas('user', function ($q) use ($filters) {
                $q->where('name', 'like', '%' . $filters['search'] . '%')
                    ->orWhere('emp_code', 'like', '%' . $filters['search'] . '%');
            });
        }

        $sortBy = $filters['sort_by'] ?? 'timestamp';
        $sortOrder = $filters['sort_order'] ?? 'desc';

        $allowedSorts = ['timestamp', 'created_at', 'user_id', 'type'];
        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, $sortOrder);
        } else {
            $query->orderBy('timestamp', 'desc');
        }

        $perPage = min($filters['per_page'] ?? 15, 100);
        return $query->paginate($perPage);
    }


    public function getFilteredAttendancesForUser(AttendanceIndexRequest $filters, int $userId): LengthAwarePaginator
    {
        $query = Attendance::with(['image:id,attendance_id,path'])
            ->where('user_id', $userId);

        if (!empty($filters['start_date'])) {
            $query->whereDate('timestamp', '>=', $filters['start_date']);
        }

        if (!empty($filters['end_date'])) {
            $query->whereDate('timestamp', '<=', $filters['end_date']);
        }

        if (!empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        $sortBy = $filters['sort_by'] ?? 'timestamp';
        $sortOrder = $filters['sort_order'] ?? 'desc';

        $allowedSorts = ['timestamp', 'created_at', 'type'];
        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, $sortOrder);
        } else {
            $query->orderBy('timestamp', 'desc');
        }

        $perPage = min($filters['per_page'] ?? 15, 100);
        return $query->paginate($perPage);
    }

    public function statsByUser($request, int $userId): array
    {

        $query = Attendance::where('user_id', $userId);

        // Aplicar filtros de fecha
        if ($request->filled('start_date')) {
            $query->whereDate('timestamp', '>=', $request->start_date);
        }

        if ($request->filled('end_date')) {
            $query->whereDate('timestamp', '<=', $request->end_date);
        }

        if ($request->filled('month') && $request->filled('year')) {
            $query->whereMonth('timestamp', $request->month)
                ->whereYear('timestamp', $request->year);
        }

        $stats = [
            'total_attendances' => $query->count(),
            'check_ins' => $query->where('type', 'check_in')->count(),
            'check_outs' => $query->where('type', 'check_out')->count(),
            'days_present' => $query->distinct('DATE(timestamp)')->count(DB::raw('DATE(timestamp)')),
            'last_attendance' => $query->latest('timestamp')->first(['timestamp', 'type']),
        ];

        return $stats;
    }

    public function createAttendance(StoreAttendanceRequest $data): Attendance
    {
        DB::beginTransaction();

        $attendance = Attendance::create(collect($data)->except('photo')->toArray());

        $imageUrl = null;
        if ($data->hasFile('photo')) {
            $disk = 'public'; // Usar disco público para acceso web
            $path = $data->file('photo')->store('attendance_photos', $disk);
            $attendance->image()->create(['path' => $path]);

            // Generar la URL completa de la imagen usando el helper
            $imageUrl = ImageHelper::getFullImageUrl($path);
        }

        $punchTime = Carbon::createFromTimestampMs($data['timestamp'], 'America/Lima')
            ->format('Y-m-d H:i:s.v O');

        try {
            // DB::connection('pgsql_external')->table('iclock_transaction')->insert([
            //     'emp_code' => $data['emp_code'],
            //     'punch_time' => $punchTime,
            //     'punch_state' => $data['type'] === 'check_in' ? 0 : 1,
            //     'verify_type' => 101,
            //     'terminal_sn' => 'App',
            //     'latitude' => $data['latitude'],
            //     'longitude' => $data['longitude'],
            //     'gps_location' => $data['address'] ?? null,
            //     'mobile' => 2,
            //     'source' => 3,
            //     'purpose' => 1,
            //     'is_attendance' => true,
            //     'upload_time' => now(),
            //     'sync_status' => 0,
            //     'emp_id' => $data['user_id'],
            //     'is_mask' => 255,
            //     'temperature' => 255,
            //     'identificador' => $data['client_id'] ?? (string) Str::uuid(),
            //     'imagen_url' => $imageUrl,
            // ]);

            DB::commit();

            $attendance->loadMissing(['user:id,name', 'image:id,attendance_id,path']);

            return $attendance;
        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error('Error creando asistencia', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new InternalErrorException('Error al insertar asistencia externa: ' . $e->getMessage());
        }
    }

    public function updateAttendance(Attendance $attendance, UpdateAttendanceRequest $data): Attendance
    {
        ds($data);


        DB::beginTransaction();


        $attendance->update(collect($data)->except('photo')->toArray());

        if ($data->hasFile('photo')) {

            // Eliminar la imagen anterior si existe
            if ($attendance->image) {
                try {
                    \Storage::disk(config('filesystems.default_disk', 'public'))->delete($attendance->image->path);
                } catch (\Exception $e) {
                    report($e);
                }
                $attendance->image()->delete();
            }

            // Almacenar la nueva imagen
            $disk = 'public'; // Usar disco público para acceso web
            $path = $data->file('photo')->store('attendance_photos', $disk);
            $attendance->image()->create(['path' => $path]);

            // Generar la URL completa de la imagen usando el helper
            $imageUrl = ImageHelper::getFullImageUrl($path);
            $attendance->update(['imagen_url' => $imageUrl]);

        }

        try {
            // DB::connection('pgsql_external')->table('iclock_transaction')
            //     ->where('id', $attendance->external_id)
            //     ->update([
            //         'punch_time' => Carbon::createFromTimestampMs($data['timestamp'], 'America/Lima')
            //             ->format('Y-m-d H:i:s.v O'),
            //         'latitude' => $data['latitude'],
            //         'longitude' => $data['longitude'],
            //         'gps_location' => $data['address'] ?? null,
            //         'imagen_url' => $imageUrl ?? $attendance->imagen_url,
            //         // 'client_id' => $data['client_id'] ?? $attendance->client_id,
            //     ]);

            $attendance->loadMissing(['user:id,name', 'image:id,attendance_id,path']);

            DB::commit();

            return $attendance;

        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error('Error actualizando asistencia', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new InternalErrorException('Error al actualizar asistencia: ' . $e->getMessage());
        }

      
    }
}