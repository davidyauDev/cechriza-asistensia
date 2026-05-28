<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreMensajeSolicitudRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'staff_id' => ['required', 'integer', 'min:1'],
            'mensaje' => ['nullable', 'string', 'max:5000', 'required_without:archivo'],
            'tipo' => ['nullable', 'in:texto,imagen,archivo'],
            'archivo' => ['nullable', 'file', 'max:10240', 'required_without:mensaje'],
            'leido' => ['nullable', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->hasFile('archivo') && ! $this->filled('tipo')) {
            $mime = (string) $this->file('archivo')->getMimeType();
            $this->merge([
                'tipo' => str_starts_with($mime, 'image/') ? 'imagen' : 'archivo',
            ]);
        }
    }
}
