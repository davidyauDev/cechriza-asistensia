<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Http\Requests\AttendanceIndexRequest;
use App\Http\Requests\StoreAttendanceRequest;
use App\Http\Resources\AttendanceResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;
use App\Helpers\ImageHelper;

class AttendanceController extends Controller
{
    public function index(AttendanceIndexRequest $request)
    {
        $query = Attendance::with(['user:id,name,emp_code', 'image:id,attendance_id,path']);

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->filled('start_date')) {
            $query->whereDate('timestamp', '>=', $request->start_date);
        }

        if ($request->filled('end_date')) {
            $query->whereDate('timestamp', '<=', $request->end_date);
        }

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('search')) {
            $query->whereHas('user', function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search . '%')
                  ->orWhere('emp_code', 'like', '%' . $request->search . '%');
            });
        }

        $sortBy = $request->get('sort_by', 'timestamp');
        $sortOrder = $request->get('sort_order', 'desc');
        
        $allowedSorts = ['timestamp', 'created_at', 'user_id', 'type'];
        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, $sortOrder);
        } else {
            $query->orderBy('timestamp', 'desc');
        }

        $perPage = min($request->get('per_page', 15), 100); 
        $attendances = $query->paginate($perPage);

        return AttendanceResource::collection($attendances);
    }

    public function forUser(AttendanceIndexRequest $request, User $user)
    {
        $query = Attendance::with(['image:id,attendance_id,path'])
            ->where('user_id', $user->id);

        if ($request->filled('start_date')) {
            $query->whereDate('timestamp', '>=', $request->start_date);
        }

        if ($request->filled('end_date')) {
            $query->whereDate('timestamp', '<=', $request->end_date);
        }

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('month') && $request->filled('year')) {
            $query->whereMonth('timestamp', $request->month)
                  ->whereYear('timestamp', $request->year);
        }

        $sortBy = $request->get('sort_by', 'timestamp');
        $sortOrder = $request->get('sort_order', 'desc');
        
        $allowedSorts = ['timestamp', 'created_at', 'type'];
        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, $sortOrder);
        } else {
            $query->orderBy('timestamp', 'desc');
        }

        $perPage = min($request->get('per_page', 25), 100);
        $attendances = $query->paginate($perPage);

        return AttendanceResource::collection($attendances);
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

        $query = Attendance::where('user_id', $user->id);

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

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'emp_code' => $user->emp_code,
            ],
            'period' => [
                'start_date' => $request->start_date,
                'end_date' => $request->end_date,
                'month' => $request->month,
                'year' => $request->year,
            ],
            'statistics' => $stats,
        ]);
    }

    public function store(StoreAttendanceRequest $request)
{
    $data = $request->validated();

    $existing = Attendance::with(['user', 'image'])
        ->where('user_id', $data['user_id'])
        ->where('client_id', $data['client_id'])
        ->first();

    if ($existing) {
        return new AttendanceResource($existing);
    }

    DB::beginTransaction();

    try {
        $attendance = Attendance::create(collect($data)->except('photo')->toArray());

        $imageUrl = null;
        if ($request->hasFile('photo')) {
            $disk = 'public'; // Usar disco público para acceso web
            $path = $request->file('photo')->store('attendance_photos', $disk);
            $attendance->image()->create(['path' => $path]);
            
            // Generar la URL completa de la imagen usando el helper
            $imageUrl = ImageHelper::getFullImageUrl($path);
        }

        $punchTime = Carbon::createFromTimestampMs($data['timestamp'], 'America/Lima')
            ->format('Y-m-d H:i:s.v O');

        DB::connection('pgsql_external')->table('iclock_transaction')->insert([
            'emp_code'      => $data['emp_code'],
            'punch_time'    => $punchTime,
            'punch_state'   => $data['type'] === 'check_in' ? 0 : 1,
            'verify_type'   => 101,
            'terminal_sn'   => 'App',
            'latitude'      => $data['latitude'],
            'longitude'     => $data['longitude'],
            'gps_location'  => $data['address'] ?? null,
            'mobile'        => 2,
            'source'        => 3,
            'purpose'       => 1,
            'is_attendance' => true,
            'upload_time'   => now(),
            'sync_status'   => 0,
            'emp_id'        => $data['user_id'],
            'is_mask'       => 255,
            'temperature'   => 255,
            'identificador' => $data['client_id'] ?? (string) Str::uuid(),
            'imagen_url'    => $imageUrl,
        ]);

        DB::commit();

        $attendance->loadMissing(['user:id,name', 'image:id,attendance_id,path']);

        return (new AttendanceResource($attendance))
            ->response()
            ->setStatusCode(201);

    } catch (Throwable $e) {
        DB::rollBack();

        Log::error('Error creando asistencia', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);

        return response()->json([
            'message' => 'Error al registrar asistencia: ' . $e->getMessage(),
        ], 500);
    }
}



    public function show(Attendance $attendance)
    {
        $attendance->loadMissing(['user', 'image']);
        return new AttendanceResource($attendance);
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
        return new AttendanceResource($attendance->load(['user', 'image']));
    }

    public function destroy(Attendance $attendance)
    {
        if ($attendance->image) {
            try {
                Storage::disk(config('filesystems.default_disk', 'public'))->delete($attendance->image->path);
            } catch (\Exception $e) {
                report($e);
            }
            $attendance->image()->delete();
        }
        $attendance->delete();
        return response()->json(null, 204);
    }
}
