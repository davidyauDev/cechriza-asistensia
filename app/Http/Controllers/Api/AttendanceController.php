<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use Illuminate\Http\Request;

class AttendanceController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $attendances = Attendance::with('user')->get();
        return response()->json($attendances);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
{
    $validated = $request->validate([
        'user_id' => ['required', 'exists:users,id'],
        'timestamp' => ['required', 'integer'],
        'latitude' => ['required', 'numeric', 'between:-90,90'],
        'longitude' => ['required', 'numeric', 'between:-180,180'],
        'notes' => ['nullable', 'string', 'max:255'],
        'device_model' => ['required', 'string', 'max:255'],
        'battery_percentage' => ['required', 'integer', 'min:0', 'max:100'],
        'signal_strength' => ['required', 'integer', 'min:0', 'max:4'],
        'network_type' => ['required', 'string', 'max:50'],
        'is_internet_available' => ['required', 'boolean'],
        'type' => ['required', 'string'],
        'photo' => ['required', 'image', 'max:5120'], 
    ]);

    $attendance = Attendance::create($validated);

    if ($request->hasFile('photo')) {
        $path = $request->file('photo')->store('attendance_photos', 'public');

        $attendance->image()->create([
            'path' => $path,
        ]);
    }

    return response()->json(['message' => 'Attendance recorded successfully'], 201);
}

    /**
     * Display the specified resource.
     */
    public function show(Attendance $attendance)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Attendance $attendance)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Attendance $attendance)
    {
        //
    }
}
