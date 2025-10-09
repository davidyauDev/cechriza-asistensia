<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class AttendanceResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array<string, mixed>
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'user' => new UserResource($this->whenLoaded('user')),
            'timestamp' => $this->timestamp,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'notes' => $this->notes,
            'device_model' => $this->device_model,
            'battery_percentage' => $this->battery_percentage,
            'signal_strength' => $this->signal_strength,
            'network_type' => $this->network_type,
            'is_internet_available' => (bool) $this->is_internet_available,
            'type' => $this->type,
            'image' => $this->whenLoaded('image', function () {
                return $this->image ? Storage::url($this->image->path) : null;
            }),
            'created_at' => optional($this->created_at)->toDateTimeString(),
            'updated_at' => optional($this->updated_at)->toDateTimeString(),
        ];
    }
}
