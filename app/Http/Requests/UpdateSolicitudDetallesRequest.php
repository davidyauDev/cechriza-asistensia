<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Log;

class UpdateSolicitudDetallesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'detalles' => ['required', 'array', 'min:1'],
            'detalles.*.id_detalle_solicitud' => ['nullable', 'integer', 'min:1'],
            'detalles.*.id_inventario' => ['required', 'integer', 'min:1'],
            'detalles.*.cantidad_solicitada' => ['required', 'integer', 'min:1'],
            'detalles.*.area_id' => ['nullable', 'integer', 'min:1'],
            'detalles.*.comentario' => ['nullable', 'string', 'max:1000'],
            'detalles.*.quitar_imagen' => ['nullable', 'boolean'],
            'detalles.*.imagen' => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:10240'],
            'detalles_eliminados' => ['nullable', 'array'],
            'detalles_eliminados.*' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'detalles.required' => 'Debes enviar al menos un detalle.',
            'detalles.array' => 'El campo detalles debe ser una lista.',
            'detalles.*.id_inventario.required' => 'Cada detalle debe incluir el inventario.',
            'detalles.*.cantidad_solicitada.required' => 'Cada detalle debe incluir la cantidad solicitada.',
            'detalles.*.imagen.image' => 'Las imagenes deben ser archivos de imagen.',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        Log::warning('solicitudes.update_detalles.failed_validation', [
            'method' => $this->method(),
            'content_type' => $this->header('content-type'),
            'all_keys' => array_keys($this->all()),
            'file_keys' => array_keys($this->allFiles()),
            'has_detalles' => $this->has('detalles'),
            'detalles_type' => gettype($this->input('detalles')),
            'detalles_preview' => $this->input('detalles'),
            'errors' => $validator->errors()->toArray(),
        ]);

        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'Error: '.$validator->errors()->first(),
            'data' => null,
        ], 422));
    }
}
