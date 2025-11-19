<?php

namespace App\Http\Requests;

use App\Models\UserRole;
use Illuminate\Foundation\Http\FormRequest;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $roles = UserRole::values();
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'string', 'min:8'],
            'emp_code' => ['nullable', 'string', 'max:255', 'unique:users'],
            'role' => ['required', 'string', 'in:' . implode(',', $roles)],
            'active' => ['sometimes', 'boolean']
        ];
    }
}
