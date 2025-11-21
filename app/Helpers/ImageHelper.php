<?php

namespace App\Helpers;
use Illuminate\Support\Facades\Storage;
use Jcupitt\Vips\Image;
use Str;
use Symfony\Component\CssSelector\Exception\InternalErrorException;
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



    public static function resizeWithVips2($file, int $maxWidth = 600)
{
    $nombre =  time() . '_' . Str::random(10) . '.' . $file->getClientOriginalExtension();
    $destino = storage_path('app/public/eventos/' . $nombre);

    // Guardar archivo original temporalmente
    $file->move(storage_path('app/public/eventos'), $nombre);

    // Ruta del ejecutable VIPS según sistema
    $vips = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN'
        ? 'C:\\vips\\bin\\vips.exe'
        : '/usr/bin/vips';

    if (!file_exists($vips)) {
        throw new InternalErrorException("❌ VIPS no encontrado en: $vips");
    }

    // Comando para redimensionar (mantiene formato original)
    $cmd = escapeshellarg($vips)
        . ' thumbnail '
        . escapeshellarg($destino) . ' '
        . escapeshellarg($destino) . ' '
        . $maxWidth
        . ' --size down';

    exec($cmd . ' 2>&1', $output, $code);

    if ($code !== 0) {
        throw new InternalErrorException(
            "❌ Error al redimensionar con VIPS:\n" . implode("\n", $output)
        );
    }

    return 'eventos/' . $nombre;
}

public static function resizeWithVips($file, int $maxWidth = 600)
{

     ds([
    'valid' => $file->isValid(),
    'error' => $file->getError(),
    'message' => $file->getErrorMessage(),
    'path' => $file
]);


    if (!$file->isValid()) {
        throw new \Exception('❌ Error al subir archivo: ' . $file->getErrorMessage());
    }

    // $originalName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
    // $extension = $file->getClientOriginalExtension();

    // Nombre limpio
    $safeName = time() . '_' . Str::random(10) . '.' . $file->getClientOriginalExtension();

    $dir = storage_path('app/public/eventos');
    $destino = $dir . DIRECTORY_SEPARATOR . $safeName;
    $temp = $dir . DIRECTORY_SEPARATOR . 'temp_' . $safeName;

    if (!file_exists($dir)) {
        mkdir($dir, 0777, true);
    }

    $file->move($dir, $safeName);

    $vips = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN'
        ? 'C:\\vips\\bin\\vips.exe'
        : '/usr/bin/vips';

    if (!file_exists($vips)) {
        throw new \Exception("VIPS no encontrado en: $vips");
    }

    $cmd = escapeshellarg($vips)
        . ' thumbnail '
        . escapeshellarg($destino) . ' '
        . escapeshellarg($temp) . ' '
        . $maxWidth
        . ' --size down';

    exec($cmd . ' 2>&1', $output, $code);

    if ($code !== 0 || !file_exists($temp)) {
        throw new \Exception(
            "❌ Error VIPS:\n" . implode("\n", $output)
        );
    }

   


    unlink($destino);
    rename($temp, $destino);

    return 'eventos/' . $safeName;
}


    public static function resizeKeepAspect($file, $maxWidth = 600)
    {
        list($width, $height) = getimagesize($file->getRealPath());

        $ratio = $height / $width;
        $newWidth = $maxWidth;
        $newHeight = intval($maxWidth * $ratio);

        return self::resizeImage($file, $newWidth, $newHeight);
    }


    public static function resizeImage($file, $newWidth = 800, $newHeight = 600)
    {
        $rutaOriginal = $file->getRealPath();
        $nombre =  time() . '_' . Str::random(10) . '.' . $file->getClientOriginalExtension();
                        
        $rutaDestino = storage_path('app/public/eventos/' . $nombre);

        list($width, $height) = getimagesize($rutaOriginal);
        $mime = $file->getMimeType();

        // Crear imagen desde archivo según tipo
        switch ($mime) {
            case 'image/jpeg':
                $src = imagecreatefromjpeg($rutaOriginal);
                break;
            case 'image/png':
                $src = imagecreatefrompng($rutaOriginal);
                break;
            case 'image/webp':
                $src = imagecreatefromwebp($rutaOriginal);
                break;
            default:
                throw new \Exception("Formato no soportado: " . $mime);
        }

        // Crear nuevo lienzo redimensionado
        $newImg = imagecreatetruecolor($newWidth, $newHeight);

        // Mantener transparencia en PNG
        if ($mime === 'image/png') {
            imagealphablending($newImg, false);
            imagesavealpha($newImg, true);
        }

        // Redimensionar
        imagecopyresampled(
            $newImg,
            $src,
            0,
            0,
            0,
            0,
            $newWidth,
            $newHeight,
            $width,
            $height
        );

        // Guardar según formato
        switch ($mime) {
            case 'image/jpeg':
                imagejpeg($newImg, $rutaDestino, 80); // calidad
                break;
            case 'image/png':
                imagepng($newImg, $rutaDestino, 8);
                break;
            case 'image/webp':
                imagewebp($newImg, $rutaDestino, 80);
                break;
        }

        // Liberar memoria
        imagedestroy($src);
        imagedestroy($newImg);

        return 'eventos/' . $nombre;
    }

    public static function fastResize($file, int $maxWidth = 600)
{
    $rutaOriginal = $file->getRealPath();

    [$width, $height] = getimagesize($rutaOriginal);

    if ($width <= $maxWidth) {
        return Storage::putFile('eventos', $file);
    }

    $ratio = $height / $width;
    $newWidth = $maxWidth;
    $newHeight = intval($maxWidth * $ratio);

    $nombre = time().'_'.bin2hex(random_bytes(5)).'.'.$file->getClientOriginalExtension();
    $destino = storage_path('app/public/eventos/'.$nombre);

    switch ($file->getMimeType()) {
        case 'image/jpeg':
            $src = imagecreatefromjpeg($rutaOriginal);
            break;
        case 'image/png':
            $src = imagecreatefrompng($rutaOriginal);
            imagealphablending($src, true);
            imagesavealpha($src, true);
            break;
        case 'image/webp':
            $src = imagecreatefromwebp($rutaOriginal);
            break;
        default:
            throw new \Exception('Formato no soportado');
    }

    $dst = imagecreatetruecolor($newWidth, $newHeight);

    imagecopyresampled($dst, $src, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

    // Guardado optimizado
    if ($file->getMimeType() === 'image/jpeg') {
        imagejpeg($dst, $destino, 75);
    } elseif ($file->getMimeType() === 'image/png') {
        imagepng($dst, $destino, 6);
    } else {
        imagewebp($dst, $destino, 80);
    }

    imagedestroy($src);
    imagedestroy($dst);

    return 'eventos/'.$nombre;
}


}