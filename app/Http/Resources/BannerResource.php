<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Banner Resource
 * 
 * Este resource transforma los datos del banner para la API.
 * La URL de la imagen se convierte automáticamente a URL completa
 * para que las aplicaciones móviles puedan acceder correctamente.
 */
class BannerResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'image_url' => $this->getFullImageUrl(),
            'status' => $this->status,
            'start_at' => optional($this->start_at)?->toISOString(),
            'end_at' => optional($this->end_at)?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }

    /**
     * Get the full URL for the image
     */
    private function getFullImageUrl(): ?string
    {
        if (!$this->image_url) {
            return null;
        }

        // Si ya es una URL completa, la devolvemos tal como está
        if (filter_var($this->image_url, FILTER_VALIDATE_URL)) {
            return $this->image_url;
        }

        // Si es una URL relativa, construimos la URL completa
        if (str_starts_with($this->image_url, '/')) {
            return rtrim(config('app.url'), '/') . $this->image_url;
        }

        // Si no tiene barra inicial, asumimos que es desde storage
        return rtrim(config('app.url'), '/') . '/storage/' . ltrim($this->image_url, '/');
    }
}
