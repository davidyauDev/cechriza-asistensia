<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;
use App\Helpers\ImageHelper;
use Illuminate\Support\Carbon;

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
            'client_id' => $this->client_id,
              'timestamp' => $this->formatTimestamp($this->timestamp),
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
                if (! $this->image || empty($this->image->path)) {
                    return null;
                }

                return ImageHelper::getFullImageUrl($this->image->path);
            }),
            'created_at' => optional($this->created_at)->toDateTimeString(),
            'updated_at' => optional($this->updated_at)->toDateTimeString(),
        ];
    }

    private function formatTimestamp($value)
{
    if (!$value) {
        return null;
    }

    try {
        return Carbon::createFromTimestampMs($value)
            ->setTimezone('America/Lima') 
            ->toIso8601String();
    } catch (\Exception $e) {
        return null; 
    }
}
}
