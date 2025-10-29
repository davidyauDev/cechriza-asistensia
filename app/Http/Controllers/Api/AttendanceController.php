<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Http\Requests\StoreAttendanceRequest;
use App\Http\Resources\AttendanceResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use OpenApi\Annotations as OA;
use Throwable;

class AttendanceController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/attendances",
     *     tags={"Attendances"},
     *     summary="List attendances",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="OK",
     *         @OA\JsonContent(type="array",
     *             @OA\Items(
     *                 type="object",
     *                 @OA\Property(property="id", type="integer"),
     *                 @OA\Property(property="user", type="object",
     *                     @OA\Property(property="id", type="integer"),
     *                     @OA\Property(property="name", type="string"),
     *                     @OA\Property(property="email", type="string", format="email")
     *                 ),
     *                 @OA\Property(property="timestamp", type="string", format="date-time"),
     *                 @OA\Property(property="latitude", type="number", format="float"),
     *                 @OA\Property(property="longitude", type="number", format="float"),
     *                 @OA\Property(property="notes", type="string")
     *             )
     *         )
     *     )
     * )
     */
    public function index()
    {
        $attendances = Attendance::with(['user', 'image'])->get();
        return AttendanceResource::collection($attendances);
    }

    /**
     * @OA\Get(
     *     path="/api/attendances/user/{id}",
     *     tags={"Attendances"},
     *     summary="Get attendances for a user",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="OK")
     * )
     */
    public function forUser(User $user)
    {
        // Paginate to avoid returning too many records; adjust per needs
        $attendances = Attendance::with(['image'])
            ->where('user_id', $user->id)
            ->orderBy('timestamp', 'desc')
            ->paginate(25);

        return AttendanceResource::collection($attendances);
    }

    /**
     * @OA\Post(
     *     path="/api/attendances",
     *     tags={"Attendances"},
     *     summary="Create attendance",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(mediaType="multipart/form-data",
     *             @OA\Schema(
                 required={"user_id","timestamp","latitude","longitude","device_model","battery_percentage","signal_strength","network_type","is_internet_available","type","photo"},
                 @OA\Property(property="user_id", type="integer", description="ID del usuario"),
                 @OA\Property(property="client_id", type="string", format="uuid", description="Identificador único del cliente/dispositivo (opcional, se genera automáticamente si no se proporciona)"),
     *                 @OA\Property(property="timestamp", type="integer", description="Timestamp en milisegundos"),
     *                 @OA\Property(property="latitude", type="number", format="float", minimum=-90, maximum=90, description="Latitud GPS"),
     *                 @OA\Property(property="longitude", type="number", format="float", minimum=-180, maximum=180, description="Longitud GPS"),
     *                 @OA\Property(property="notes", type="string", maxLength=255, description="Notas adicionales (opcional)"),
     *                 @OA\Property(property="device_model", type="string", maxLength=255, description="Modelo del dispositivo"),
     *                 @OA\Property(property="battery_percentage", type="integer", minimum=0, maximum=100, description="Porcentaje de batería"),
     *                 @OA\Property(property="signal_strength", type="integer", minimum=0, maximum=4, description="Fuerza de la señal"),
     *                 @OA\Property(property="network_type", type="string", maxLength=50, description="Tipo de red"),
     *                 @OA\Property(property="is_internet_available", type="boolean", description="Disponibilidad de internet"),
     *                 @OA\Property(property="type", type="string", description="Tipo de asistencia"),
     *                 @OA\Property(property="photo", type="string", format="binary", description="Foto de asistencia (máximo 5MB)")
     *             )
     *         ),
     *         @OA\MediaType(mediaType="application/json",
     *             @OA\Schema(
                 required={"user_id","timestamp","latitude","longitude","device_model","battery_percentage","signal_strength","network_type","is_internet_available","type"},
                 @OA\Property(property="user_id", type="integer", description="ID del usuario"),
                 @OA\Property(property="client_id", type="string", format="uuid", description="Identificador único del cliente/dispositivo (opcional, se genera automáticamente si no se proporciona)"),
     *                 @OA\Property(property="timestamp", type="integer", description="Timestamp en milisegundos"),
     *                 @OA\Property(property="latitude", type="number", format="float", minimum=-90, maximum=90, description="Latitud GPS"),
     *                 @OA\Property(property="longitude", type="number", format="float", minimum=-180, maximum=180, description="Longitud GPS"),
     *                 @OA\Property(property="notes", type="string", maxLength=255, description="Notas adicionales (opcional)"),
     *                 @OA\Property(property="device_model", type="string", maxLength=255, description="Modelo del dispositivo"),
     *                 @OA\Property(property="battery_percentage", type="integer", minimum=0, maximum=100, description="Porcentaje de batería"),
     *                 @OA\Property(property="signal_strength", type="integer", minimum=0, maximum=4, description="Fuerza de la señal"),
     *                 @OA\Property(property="network_type", type="string", maxLength=50, description="Tipo de red"),
     *                 @OA\Property(property="is_internet_available", type="boolean", description="Disponibilidad de internet"),
     *                 @OA\Property(property="type", type="string", description="Tipo de asistencia")
     *             ),
     *             example={
     *                 "user_id": 1,
     *                 "client_id": "550e8400-e29b-41d4-a716-446655440000",
     *                 "timestamp": 1698066000000,
     *                 "latitude": -12.0464,
     *                 "longitude": -77.0428,
     *                 "notes": "Llegada puntual",
     *                 "device_model": "iPhone 14",
     *                 "battery_percentage": 85,
     *                 "signal_strength": 4,
     *                 "network_type": "5G",
     *                 "is_internet_available": true,
     *                 "type": "entrada"
     *             }
     *         )
     *     ),
     *     @OA\Response(response=201, description="Created"),
     *     @OA\Response(response=400, description="Bad Request")
     * )
     */
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

        if ($request->hasFile('photo')) {
            $disk = config('filesystems.default_disk', 'public');
            $path = $request->file('photo')->store('attendance_photos', $disk);
            $attendance->image()->create(['path' => $path]);
        }

        $punchTime = Carbon::createFromTimestampMs($data['timestamp'], 'America/Lima')
            ->format('Y-m-d H:i:s.v O');

        DB::connection('pgsql_external')->table('iclock_transaction')->insert([
            'emp_code'      => '70994384',
            'punch_time'    => $punchTime,
            'punch_state'   => $data['type'] === 'ENTRADA' ? 0 : 1,
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

    /**
     * @OA\Get(
     *     path="/api/attendances/{attendance}",
     *     tags={"Attendances"},
     *     summary="Get attendance",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="attendance", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="OK"),
     *     @OA\Response(response=404, description="Not Found")
     * )
     */


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

    /**
     * @OA\Put(
     *     path="/api/attendances/{attendance}",
     *     tags={"Attendances"},
     *     summary="Update attendance",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="attendance", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(@OA\JsonContent(
     *         @OA\Property(property="notes", type="string"),
     *         @OA\Property(property="device_model", type="string"),
     *         @OA\Property(property="battery_percentage", type="integer"),
     *         @OA\Property(property="signal_strength", type="integer"),
     *         @OA\Property(property="network_type", type="string")
     *     )),
     *     @OA\Response(response=200, description="OK"),
     *     @OA\Response(response=404, description="Not Found")
     * )
     */


    /**
     * @OA\Delete(
     *     path="/api/attendances/{attendance}",
     *     tags={"Attendances"},
     *     summary="Delete attendance",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="attendance", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=204, description="No Content"),
     *     @OA\Response(response=404, description="Not Found")
     * )
     */
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

    /**
     * @OA\Delete(
     *     path="/api/attendances/{attendance}",
     *     tags={"Attendances"},
     *     summary="Delete attendance",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="attendance", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=204, description="No Content"),
     *     @OA\Response(response=404, description="Not Found")
     * )
     */
}
