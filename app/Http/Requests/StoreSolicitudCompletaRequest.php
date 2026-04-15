<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreSolicitudCompletaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $categories = ['insumos', 'ssgg', 'rrhh'];
        $rules = [
            'id_usuario_solicitante' => ['required', 'integer', 'min:1'],
            'justificacion' => ['nullable', 'string', 'max:1000'],
            'es_pedido_compra' => ['required', 'boolean'],
            'es_provincia' => ['required', 'boolean'],
            'ubicacion' => ['required', 'string', 'in:LIMA,PROVINCIA'],
            'prioridad' => ['nullable', 'string', 'in:Baja,Media,Alta,Urgente'],
            'fecha_necesaria' => ['nullable', 'date'],
            'tipo_entrega_preferida' => ['nullable', 'string', 'in:Directo,Delivery'],
            'id_direccion_entrega' => ['nullable', 'integer', 'min:1'],
            'id_area' => ['nullable', 'array'],
            'id_area.*' => ['nullable', 'integer', 'min:1'],
        ];

        foreach ($categories as $category) {
            $rules["id_producto_{$category}"] = ['nullable', 'array'];
            $rules["id_producto_{$category}.*"] = ['nullable', 'integer'];
            $rules["cantidad_{$category}"] = ['nullable', 'array'];
            $rules["cantidad_{$category}.*"] = ['nullable', 'integer'];
            $rules["id_area_{$category}"] = ['nullable', 'array'];
            $rules["id_area_{$category}.*"] = ['nullable', 'integer', 'min:1'];
            $rules["observacion_{$category}"] = ['nullable', 'array'];
            $rules["observacion_{$category}.*"] = ['nullable', 'string', 'max:1000'];
            $rules["foto_{$category}"] = ['nullable', 'array'];
            $rules["foto_{$category}.*"] = ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:10240'];
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'id_usuario_solicitante.required' => 'El usuario solicitante es obligatorio.',
            'id_usuario_solicitante.integer' => 'El usuario solicitante debe ser un número entero.',
            'es_pedido_compra.required' => 'Debes indicar si es pedido de compra.',
            'es_pedido_compra.boolean' => 'El campo es_pedido_compra debe ser 0 o 1.',
            'es_provincia.required' => 'Debes indicar si es provincia.',
            'es_provincia.boolean' => 'El campo es_provincia debe ser 0 o 1.',
            'prioridad.in' => 'La prioridad debe ser Baja, Media, Alta o Urgente.',
            'tipo_entrega_preferida.in' => 'El tipo de entrega preferida debe ser Directo o Delivery.',
            'ubicacion.required' => 'Debes indicar la ubicación.',
            'ubicacion.string' => 'La ubicación debe ser texto.',
            'ubicacion.in' => 'La ubicación debe ser LIMA o PROVINCIA.',
            'id_direccion_entrega.integer' => 'La dirección de entrega debe ser un número entero.',
            '*.array' => 'El formato de uno de los campos enviados no es válido.',
            '*.integer' => 'Uno de los valores enviados debe ser numérico.',
            '*.string' => 'Uno de los valores enviados debe ser texto.',
            '*.image' => 'Uno de los archivos enviados debe ser una imagen.',
            '*.mimes' => 'Las fotos deben ser de tipo jpg, jpeg, png, webp o gif.',
            '*.max' => 'Uno de los archivos supera el tamaño permitido.',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'Error: '.$validator->errors()->first(),
            'data' => null,
        ], 422));
    }
}
