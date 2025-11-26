<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Log;

class UpdateAttendanceRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {

        return [
            'user_id' => ['sometimes', 'exists:users,id'],
            'emp_code' => ['sometimes', 'string'],            
            'address' => ['sometimes', 'nullable', 'string'],
            'client_id' => ['sometimes', 'uuid'], 
            'timestamp' => ['sometimes'],
            'latitude' => ['sometimes', 'numeric', 'between:-90,90'],
            'longitude' => ['sometimes', 'numeric', 'between:-180,180'],
            'notes' => ['nullable', 'string', 'max:255'],
            'device_model' => ['sometimes', 'string', 'max:255'],
            'battery_percentage' => ['sometimes', 'integer', 'min:0', 'max:100'],
            'signal_strength' => ['sometimes', 'integer', 'min:0', 'max:4'],
            'network_type' => ['sometimes', 'string', 'max:50'],
            'is_internet_available' => ['sometimes', 'boolean'],
            'type' => ['sometimes', 'string'],
            'photo' => ['nullable'],
            'imagen_url' => ['nullable', 'url'], 
        ];
    }

    protected function prepareForValidation()
    {
        if ($this->has('is_internet_available')) {
            $this->merge([
                'is_internet_available' => filter_var($this->input('is_internet_available'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE),
            ]);
        }
    }
}
