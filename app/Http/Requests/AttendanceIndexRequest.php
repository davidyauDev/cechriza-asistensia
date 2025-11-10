<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AttendanceIndexRequest extends FormRequest
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
            'user_id' => 'nullable|exists:users,id',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'type' => 'nullable|in:check_in,check_out',
            'search' => 'nullable|string|max:255',
            'sort_by' => 'nullable|string|in:timestamp,created_at,user_id,type',
            'sort_order' => 'nullable|string|in:asc,desc',
            'per_page' => 'nullable|integer|min:1|max:100',
            'month' => 'nullable|integer|min:1|max:12',
            'year' => 'nullable|integer|min:2020|max:' . (date('Y') + 1),
        ];
    }

    /**
     * Get custom validation messages.
     */
    public function messages(): array
    {
        return [
            'user_id.exists' => 'El usuario especificado no existe.',
            'end_date.after_or_equal' => 'La fecha de fin debe ser posterior o igual a la fecha de inicio.',
            'type.in' => 'El tipo debe ser check_in o check_out.',
            'sort_by.in' => 'El campo de ordenamiento no es válido.',
            'sort_order.in' => 'El orden debe ser asc o desc.',
            'per_page.max' => 'Máximo 100 registros por página.',
            'month.between' => 'El mes debe estar entre 1 y 12.',
            'year.min' => 'El año debe ser mayor a 2019.',
        ];
    }
}