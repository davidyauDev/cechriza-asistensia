<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class EventoRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'titulo' => 'required|string|max:255',
            'descripcion' => 'required|string',
            'fecha_inicio' => 'required|date|after_or_equal:today',
            'fecha_fin' => 'required|date|after_or_equal:fecha_inicio',
            'estado' => 'sometimes|in:programado,activo,finalizado',
            
            // Validaciones para archivos de imagen subidos
            'imagenes_archivos' => 'sometimes|array|max:10',
            'imagenes_archivos.*' => 'image|mimes:jpeg,png,jpg,gif,webp|max:5120', // 5MB máximo
            
            // Validaciones para las imágenes por URL (para compatibilidad)
            'imagenes' => 'sometimes|array|max:10', // Máximo 10 imágenes
            'imagenes.*.url_imagen' => 'required|string|url',
            'imagenes.*.descripcion' => 'sometimes|string|max:500',
            'imagenes.*.orden' => 'sometimes|integer|min:1',
            'imagenes.*.autor' => 'sometimes|string|max:100',
            
            // Metadatos para archivos subidos
            'descripcion_imagenes' => 'sometimes|array',
            'descripcion_imagenes.*' => 'sometimes|string|max:500',
            'autor_imagenes' => 'sometimes|array',
            'autor_imagenes.*' => 'sometimes|string|max:100',
        ];
    }

    /**
     * Get the validation error messages.
     */
    public function messages(): array
    {
        return [
            'titulo.required' => 'El título del evento es obligatorio',
            'titulo.max' => 'El título no puede exceder 255 caracteres',
            'descripcion.required' => 'La descripción del evento es obligatoria',
            'fecha_inicio.required' => 'La fecha de inicio es obligatoria',
            'fecha_inicio.date' => 'La fecha de inicio debe ser una fecha válida',
            'fecha_inicio.after_or_equal' => 'La fecha de inicio no puede ser anterior a hoy',
            'fecha_fin.required' => 'La fecha de fin es obligatoria',
            'fecha_fin.date' => 'La fecha de fin debe ser una fecha válida',
            'fecha_fin.after_or_equal' => 'La fecha de fin debe ser posterior o igual a la fecha de inicio',
            'estado.in' => 'El estado debe ser: programado, activo o finalizado',
            
            // Mensajes para archivos de imagen
            'imagenes_archivos.array' => 'Los archivos de imagen deben ser un array',
            'imagenes_archivos.max' => 'No se pueden subir más de 10 imágenes',
            'imagenes_archivos.*.image' => 'El archivo debe ser una imagen',
            'imagenes_archivos.*.mimes' => 'La imagen debe ser de tipo: jpeg, png, jpg, gif o webp',
            'imagenes_archivos.*.max' => 'La imagen no puede ser mayor a 5MB',
            
            // Mensajes para imágenes por URL
            'imagenes.array' => 'Las imágenes deben ser un array',
            'imagenes.max' => 'No se pueden agregar más de 10 imágenes',
            'imagenes.*.url_imagen.required' => 'La URL de la imagen es obligatoria',
            'imagenes.*.url_imagen.url' => 'La URL de la imagen debe ser válida',
            'imagenes.*.descripcion.max' => 'La descripción de la imagen no puede exceder 500 caracteres',
            'imagenes.*.orden.integer' => 'El orden debe ser un número entero',
            'imagenes.*.orden.min' => 'El orden debe ser mayor a 0',
            'imagenes.*.autor.max' => 'El nombre del autor no puede exceder 100 caracteres',
            
            // Mensajes para metadatos
            'descripcion_imagenes.*.max' => 'La descripción de la imagen no puede exceder 500 caracteres',
            'autor_imagenes.*.max' => 'El nombre del autor no puede exceder 100 caracteres',
        ];
    }

    /**
     * Handle a failed validation attempt.
     */
    protected function failedValidation(\Illuminate\Contracts\Validation\Validator $validator)
    {
        throw new \Illuminate\Http\Exceptions\HttpResponseException(
            response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors' => $validator->errors()
            ], 422)
        );
    }
}
