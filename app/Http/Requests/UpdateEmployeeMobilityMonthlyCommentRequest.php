<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateEmployeeMobilityMonthlyCommentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'employee_id' => ['required', 'integer', 'exists:pgsql_external.personnel_employee,id'],
            'period_month' => ['required', 'date_format:Y-m-d'],
            'monthly_comment' => ['required', 'string'],
        ];
    }
}

