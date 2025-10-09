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

class AttendanceController extends Controller
{

    public function index()
    {
        $attendances = Attendance::with(['user', 'image'])->get();
        return AttendanceResource::collection($attendances);
    }

    /**
     * Return attendances for a given user.
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
