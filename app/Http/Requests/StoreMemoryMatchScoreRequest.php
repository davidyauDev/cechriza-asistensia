<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreMemoryMatchScoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', 'min:1'],
            'user_name' => ['required', 'string', 'max:255'],
            'moves' => ['required', 'integer', 'gt:0'],
            'elapsed_seconds' => ['required', 'integer', 'gt:0'],
            'matched_pairs' => ['required', 'integer', 'gt:0'],
            'played_at' => ['nullable', 'date'],
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

