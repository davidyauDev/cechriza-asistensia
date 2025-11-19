<?php

namespace App\Http\Requests;

use App\Models\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $userId = $this->route('user')?->id ?? $this->route('id');
  $roles = array_column(UserRole::cases(), 'value');
      
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => [
                'sometimes',
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('users')->ignore($userId),
            ],
            'password' => ['nullable', 'string', 'min:8'],
            'role' => [
                'sometimes',
                'required',
                'string',
                'in:' . implode(',', $roles),
            ],
            'active' => ['sometimes', 'boolean'],
            'emp_code' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('users')->ignore($userId),
            ],
        ];
    }
}
