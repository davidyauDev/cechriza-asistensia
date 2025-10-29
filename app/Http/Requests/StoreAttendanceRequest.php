<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Log;

class StoreAttendanceRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'user_id' => ['required', 'exists:users,id'],
            'client_id' => ['required', 'uuid'], 
            'timestamp' => ['required'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'notes' => ['nullable', 'string', 'max:255'],
            'device_model' => ['required', 'string', 'max:255'],
            'battery_percentage' => ['required', 'integer', 'min:0', 'max:100'],
            'signal_strength' => ['required', 'integer', 'min:0', 'max:4'],
            'network_type' => ['required', 'string', 'max:50'],
            'is_internet_available' => ['required', 'boolean'],
            'type' => ['required', 'string'],
            'photo' => ['nullable', 'image', 'max:5120'], 
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
