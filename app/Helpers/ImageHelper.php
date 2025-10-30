<?php

namespace App\Helpers;

class ImageHelper
{
    /**
     * Genera la URL completa para acceder a una imagen
     * 
     * @param string|null $imagePath
     * @return string|null
     */
    public static function getFullImageUrl(?string $imagePath): ?string
    {
        if (!$imagePath) {
            return null;
        }

        // Si ya es una URL completa, la devolvemos tal como está
        if (filter_var($imagePath, FILTER_VALIDATE_URL)) {
            return $imagePath;
        }

        // Si es una URL relativa que comienza con /, construimos la URL completa
        if (str_starts_with($imagePath, '/')) {
            return rtrim(config('app.url'), '/') . $imagePath;
        }

        // Si no tiene barra inicial, asumimos que es desde storage
        return rtrim(config('app.url'), '/') . '/storage/' . ltrim($imagePath, '/');
    }

    /**
     * Genera una URL para una imagen almacenada en el disco público
     * 
     * @param string $path
     * @return string
     */
    public static function getStorageUrl(string $path): string
    {
        return self::getFullImageUrl($path);
    }

    /**
     * Verifica si una URL de imagen es válida
     * 
     * @param string|null $imageUrl
     * @return bool
     */
    public static function isValidImageUrl(?string $imageUrl): bool
    {
        if (!$imageUrl) {
            return false;
        }

        return filter_var($imageUrl, FILTER_VALIDATE_URL) !== false;
    }
}