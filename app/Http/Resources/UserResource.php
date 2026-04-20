<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;
use Throwable;


class UserResource extends JsonResource
{
    private static array $staffIdByEmpCode = [];
    
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
            'role' => $this->role,
            'active' => Boolval($this->active),
            'attendances' => AttendanceResource::collection($this->whenLoaded('attendances')),
            'deleted_at' => $this->deleted_at,
        ];
    }

    private function resolveExternalStaffId(): ?int
    {
        $empCode = $this->emp_code ? (string) $this->emp_code : null;
        if (!$empCode) {
            return null;
        }

        if (array_key_exists($empCode, self::$staffIdByEmpCode)) {
            return self::$staffIdByEmpCode[$empCode];
        }

        try {
            $staffId = DB::connection('mysql_external')
                ->table('ost_staff')
                ->where('dni', $empCode)
                ->value('staff_id');

            return self::$staffIdByEmpCode[$empCode] = $staffId !== null ? (int) $staffId : null;
        } catch (Throwable) {
            return self::$staffIdByEmpCode[$empCode] = null;
        }
    }
}
