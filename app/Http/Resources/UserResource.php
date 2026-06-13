<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;
use Throwable;


class UserResource extends JsonResource
{
    private static array $staffByEmpCode = [];
    
    public function toArray($request)
    {
        if (empty($this->resource)) {
            return null;
        }
        
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'emp_code' => $this->emp_code,
            'staff_id' => $this->resolveExternalStaffId(),
            'id_cargo' => $this->resolveExternalStaffCargoId(),
            'role' => $this->role,
            'active' => Boolval($this->active),
            'attendances' => AttendanceResource::collection($this->whenLoaded('attendances')),
            'deleted_at' => $this->deleted_at,
        ];
    }

    private function resolveExternalStaffId(): ?int
    {
        return $this->resolveExternalStaffData()['staff_id'];
    }

    private function resolveExternalStaffCargoId(): ?int
    {
        return $this->resolveExternalStaffData()['id_cargo'];
    }

    private function resolveExternalStaffData(): array
    {
        $empCode = $this->emp_code ? (string) $this->emp_code : null;
        if (!$empCode) {
            return ['staff_id' => null, 'id_cargo' => null];
        }

        if (array_key_exists($empCode, self::$staffByEmpCode)) {
            return self::$staffByEmpCode[$empCode];
        }

        try {
            $staff = DB::connection('mysql_external')
                ->table('ost_staff')
                ->where('dni', $empCode)
                ->first(['staff_id', 'id_cargo']);

            return self::$staffByEmpCode[$empCode] = [
                'staff_id' => $staff?->staff_id !== null ? (int) $staff->staff_id : null,
                'id_cargo' => $staff?->id_cargo !== null ? (int) $staff->id_cargo : null,
            ];
        } catch (Throwable) {
            return self::$staffByEmpCode[$empCode] = ['staff_id' => null, 'id_cargo' => null];
        }
    }
}
