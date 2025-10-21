<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBannerRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'image_url' => ['sometimes', 'nullable', 'url'],
            'media' => ['sometimes', 'nullable', 'file', 'image', 'max:5120'],
            'status' => ['sometimes', 'required', 'in:draft,published,archived'],
            'start_at' => ['sometimes', 'nullable', 'date', 'before_or_equal:end_at'],
            'end_at' => ['sometimes', 'nullable', 'date', 'after_or_equal:start_at'],
        ];
    }

    public function prepareForValidation()
    {
        if ($this->has('status')) {
            $this->merge(['status' => strtolower($this->input('status'))]);
        }
    }
}
