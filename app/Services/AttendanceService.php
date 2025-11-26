<?php


namespace App\Services;

use App\Http\Requests\AttendanceIndexRequest;
use App\Http\Requests\StoreAttendanceRequest;
use App\Http\Requests\UpdateAttendanceRequest;
use App\Http\Resources\AttendanceResource;
use App\Models\Attendance;
use App\Models\User;
use App\Services\AttendanceServiceInterface;
use App\Repositories\AttendanceServiceRepositoryInterface;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Storage;



class AttendanceService implements AttendanceServiceInterface
{

    public function __construct(
        private AttendanceServiceRepositoryInterface $attendanceRepository
    ) {
        //
    }
    public function index(AttendanceIndexRequest $request): AnonymousResourceCollection
    {
        $attendances = $this->attendanceRepository->getFilteredAttendances($request);
        return AttendanceResource::collection($attendances);

    }

    public function forUser(AttendanceIndexRequest $request, int $userId): AnonymousResourceCollection
    {
        $attendances = $this->attendanceRepository->getFilteredAttendancesForUser($request, $userId);
        return AttendanceResource::collection($attendances);
    }

    public function statsByUser(array $request, User $user): array
    {
        $stats = $this->attendanceRepository->statsByUser($request, $user->id);

        return [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'emp_code' => $user->emp_code,
            ],
            'period' => [
                'start_date' => $request['start_date'] ?? null,
                'end_date' => $request['end_date'] ?? null,
                'month' => $request['month'] ?? null,
                'year' => $request['year'] ?? null,
            ],
            'statistics' => $stats,
        ];
    }

    public function store(StoreAttendanceRequest $data): AttendanceResource
    {
        $existing = Attendance::with(['user', 'image'])
            ->where('user_id', $data['user_id'])
            ->where('client_id', $data['client_id'])
            ->first();

        if ($existing) {
            return new AttendanceResource($existing);
        }

        $attendance = $this->attendanceRepository->createAttendance($data);

        return new AttendanceResource($attendance);


    }

    public function update(Attendance $attendance, UpdateAttendanceRequest $data): AttendanceResource
    {
     
        $updatedAttendance = $this->attendanceRepository->updateAttendance($attendance, $data);

        return new AttendanceResource($updatedAttendance);
    }

   

    public function delete(Attendance $attendance): void
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
    }
}
