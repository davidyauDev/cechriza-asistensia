<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBannerRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'image_url' => ['nullable'],
            'media' => ['nullable', 'file', 'image', 'max:5120'], // optional upload
            'status' => ['required', 'in:draft,published,archived'],
            'start_at' => ['nullable', 'date', 'before_or_equal:end_at'],
            'end_at' => ['nullable', 'date', 'after_or_equal:start_at'],
        ];
    }

    public function prepareForValidation()
    {
        // Normalize status
        if ($this->has('status')) {
            $this->merge(['status' => strtolower($this->input('status'))]);
        }
    }
}
