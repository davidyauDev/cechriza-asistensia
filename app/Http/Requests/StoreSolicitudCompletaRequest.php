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
            'ubicacion' => ['nullable', 'string', 'in:LIMA,PROVINCIA'],
            'id_ubicacion' => ['nullable', 'integer', 'min:1'],
            'prioridad' => ['nullable', 'string', 'in:Baja,Media,Alta,Urgente'],
            'fecha_necesaria' => ['nullable', 'date'],
            'tipo_entrega_preferida' => ['nullable', 'string', 'in:Directo,Delivery'],
            'id_direccion_entrega' => ['nullable', 'integer', 'min:1'],
            'id_area' => ['nullable', 'array'],
            'id_area.*' => ['nullable', 'integer', 'min:1'],
            'solicitud_gasto_detalles' => ['nullable', 'array'],
            'solicitud_gasto_detalles.*.categoria' => ['nullable', 'string'],
            'solicitud_gasto_detalles.*.category' => ['nullable', 'string'],
            'solicitud_gasto_detalles.*.id_inventario' => ['nullable', 'integer', 'min:1'],
            'solicitud_gasto_detalles.*.id_producto' => ['nullable', 'integer', 'min:1'],
            'solicitud_gasto_detalles.*.cantidad' => ['nullable', 'integer', 'min:1'],
            'solicitud_gasto_detalles.*.quantity' => ['nullable', 'integer', 'min:1'],
            'solicitud_gasto_detalles.*.id_area' => ['nullable', 'integer', 'min:1'],
            'solicitud_gasto_detalles.*.area_id' => ['nullable', 'integer', 'min:1'],
            'solicitud_gasto_detalles.*.id_ubicacion_limpieza' => ['nullable', 'integer', 'min:1'],
            'solicitud_gasto_detalles.*.descripcion_adicional' => ['nullable', 'string', 'max:1000'],
            'items' => ['nullable', 'array'],
            'items.*.categoria' => ['nullable', 'string'],
            'items.*.category' => ['nullable', 'string'],
            'items.*.id_inventario' => ['nullable', 'integer', 'min:1'],
            'items.*.id_producto' => ['nullable', 'integer', 'min:1'],
            'items.*.cantidad' => ['nullable', 'integer', 'min:1'],
            'items.*.quantity' => ['nullable', 'integer', 'min:1'],
            'items.*.id_area' => ['nullable', 'integer', 'min:1'],
            'items.*.area_id' => ['nullable', 'integer', 'min:1'],
            'items.*.id_ubicacion' => ['nullable', 'integer', 'min:1'],
            'items.*.location_id' => ['nullable', 'integer', 'min:1'],
            'items.*.id_ubicacion_limpieza' => ['nullable', 'integer', 'min:1'],
            'items.*.ubicacion' => ['nullable', 'string', 'in:LIMA,PROVINCIA'],
            'items.*.location' => ['nullable', 'string', 'in:LIMA,PROVINCIA'],
            'items.*.descripcion_adicional' => ['nullable', 'string', 'max:1000'],
            'items.*.observacion' => ['nullable', 'string', 'max:1000'],
            'items.*.observation' => ['nullable', 'string', 'max:1000'],
            'items.*.imagen' => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:10240'],
            'items.*.foto' => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:10240'],
            'items.*.image' => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:10240'],
        ];

        foreach ($categories as $category) {
            $rules["id_producto_{$category}"] = ['nullable', 'array'];
            $rules["id_producto_{$category}.*"] = ['nullable', 'integer'];
            $rules["cantidad_{$category}"] = ['nullable', 'array'];
            $rules["cantidad_{$category}.*"] = ['nullable', 'integer'];
            $rules["id_area_{$category}"] = ['nullable', 'array'];
            $rules["id_area_{$category}.*"] = ['nullable', 'integer', 'min:1'];
            $rules["id_ubicacion_{$category}"] = ['nullable', 'array'];
            $rules["id_ubicacion_{$category}.*"] = ['nullable', 'integer', 'min:1'];
            $rules["id_ubicacion_limpieza_{$category}"] = ['nullable', 'array'];
            $rules["id_ubicacion_limpieza_{$category}.*"] = ['nullable', 'integer', 'min:1'];
            $rules["ubicacion_{$category}"] = ['nullable', 'array'];
            $rules["ubicacion_{$category}.*"] = ['nullable', 'string', 'in:LIMA,PROVINCIA'];
            $rules["descripcion_adicional_{$category}"] = ['nullable', 'array'];
            $rules["descripcion_adicional_{$category}.*"] = ['nullable', 'string', 'max:1000'];
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
            'id_usuario_solicitante.integer' => 'El usuario solicitante debe ser un numero entero.',
            'es_pedido_compra.required' => 'Debes indicar si es pedido de compra.',
            'es_pedido_compra.boolean' => 'El campo es_pedido_compra debe ser 0 o 1.',
            'prioridad.in' => 'La prioridad debe ser Baja, Media, Alta o Urgente.',
            'tipo_entrega_preferida.in' => 'El tipo de entrega preferida debe ser Directo o Delivery.',
            'ubicacion.string' => 'La ubicacion debe ser texto.',
            'ubicacion.in' => 'La ubicacion debe ser LIMA o PROVINCIA.',
            'id_ubicacion.integer' => 'La ubicacion debe ser un numero entero.',
            'id_direccion_entrega.integer' => 'La direccion de entrega debe ser un numero entero.',
            '*.array' => 'El formato de uno de los campos enviados no es valido.',
            '*.integer' => 'Uno de los valores enviados debe ser numerico.',
            '*.string' => 'Uno de los valores enviados debe ser texto.',
            '*.image' => 'Uno de los archivos enviados debe ser una imagen.',
            '*.mimes' => 'Las fotos deben ser de tipo jpg, jpeg, png, webp o gif.',
            '*.max' => 'Uno de los archivos supera el tamano permitido.',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            if ($this->hasLocationData()) {
                return;
            }

            $validator->errors()->add('ubicacion', 'Debes indicar la ubicacion en la solicitud o en cada detalle.');
        });
    }

    protected function hasLocationData(): bool
    {
        $ubicacion = trim((string) $this->input('ubicacion', ''));
        if ($ubicacion !== '') {
            return true;
        }

        $items = $this->input('items', []);
        if (is_array($items)) {
            foreach ($items as $item) {
                if (! is_array($item)) {
                    continue;
                }

                $itemUbicacion = trim((string) ($item['ubicacion'] ?? $item['location'] ?? ''));
                $itemUbicacionId = (int) ($item['id_ubicacion_limpieza'] ?? $item['id_ubicacion'] ?? $item['location_id'] ?? 0);
                if ($itemUbicacion !== '' || $itemUbicacionId > 0) {
                    return true;
                }
            }
        }

        foreach (['insumos', 'ssgg', 'rrhh'] as $category) {
            foreach (['ubicacion', 'location', 'id_ubicacion_limpieza', 'id_ubicacion', 'location_id'] as $field) {
                $values = $this->input("{$field}_{$category}", []);
                if (is_array($values)) {
                    foreach ($values as $value) {
                        if ($field === 'id_ubicacion_limpieza' || $field === 'id_ubicacion' || $field === 'location_id') {
                            if ((int) $value > 0) {
                                return true;
                            }

                            continue;
                        }

                        if (trim((string) $value) !== '') {
                            return true;
                        }
                    }
                }
            }
        }

        return false;
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
