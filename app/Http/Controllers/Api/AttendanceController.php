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
use OpenApi\Annotations as OA;

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
     *                 required={"user_id","timestamp"},
     *                 @OA\Property(property="user_id", type="integer"),
     *                 @OA\Property(property="timestamp", type="string", format="date-time"),
     *                 @OA\Property(property="latitude", type="number", format="float"),
     *                 @OA\Property(property="longitude", type="number", format="float"),
     *                 @OA\Property(property="photo", type="string", format="binary")
     *             )
     *         ),
     *         @OA\MediaType(mediaType="application/json",
     *             @OA\Schema(
     *                 required={"user_id","timestamp"},
     *                 @OA\Property(property="user_id", type="integer"),
     *                 @OA\Property(property="timestamp", type="string", format="date-time"),
     *                 @OA\Property(property="latitude", type="number", format="float"),
     *                 @OA\Property(property="longitude", type="number", format="float")
     *             ),
     *             example={"user_id":1,"timestamp":"2025-10-20T12:00:00Z","latitude":-12.0464,"longitude":-77.0428}
     *         )
     *     ),
     *     @OA\Response(response=201, description="Created"),
     *     @OA\Response(response=400, description="Bad Request")
     * )
     */
    public function store(StoreAttendanceRequest $request)
    {
        $data = $request->validated();
        DB::beginTransaction();
        try {
            $attendance = Attendance::create(collect($data)->except('photo')->toArray());
            if ($request->hasFile('photo')) {
                $path = $request->file('photo')->store('attendance_photos', config('filesystems.default_disk', 'public'));

                $attendance->image()->create(['path' => $path]);
            }
            DB::commit();
            $attendance->load(['user', 'image']);

            return (new AttendanceResource($attendance))->response()->setStatusCode(201);
        } catch (\Exception $e) {
            DB::rollBack();
            report($e);
            return response()->json(['message' => 'Could not record attendance'], 500);
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
