<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TechnicianResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'department' => $this->department,
            'position' => $this->position,
            'status' => $this->status,
            'routes' => $this->routes ?? [],
            'created_at' => $this->when(isset($this->created_at), function () {
                return $this->created_at?->toISOString();
            }),
            'updated_at' => $this->when(isset($this->updated_at), function () {
                return $this->updated_at?->toISOString();
            }),
        ];
    }
}
