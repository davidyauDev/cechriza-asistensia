<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;


class UserResource extends JsonResource
{
    
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
            'role' => $this->role,
            'active' => Boolval($this->active),
            'attendances' => AttendanceResource::collection($this->whenLoaded('attendances')),
            'deleted_at' => $this->deleted_at,
        ];
    }
}
