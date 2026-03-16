<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreEmployeeMobilityMonthlyCommentRequest;
use App\Http\Requests\UpdateEmployeeMobilityMonthlyCommentRequest;
use App\Models\EmployeeMobilityMonthlyComment;
use App\Traits\ApiResponseTrait;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

class EmployeeMobilityMonthlyCommentController extends Controller
{
    use ApiResponseTrait;

    private function normalizePeriodMonth(string $periodMonth): string
    {
        return CarbonImmutable::createFromFormat('Y-m-d', $periodMonth)
            ->startOfMonth()
            ->toDateString();
    }

    public function show(Request $request)
    {
        $validated = $request->validate([
            'employee_id' => ['required', 'integer'],
            'period_month' => ['required', 'date_format:Y-m-d'],
        ]);

        $periodMonth = $this->normalizePeriodMonth($validated['period_month']);

        $comment = EmployeeMobilityMonthlyComment::query()
            ->where('employee_id', $validated['employee_id'])
            ->where('period_month', $periodMonth)
            ->first();

        if (! $comment) {
            return $this->errorResponse('Comentario mensual no encontrado.', 404);
        }

        return $this->successResponse($comment, 'Comentario mensual obtenido.');
    }

    public function store(StoreEmployeeMobilityMonthlyCommentRequest $request)
    {
        $validated = $request->validated();
        $validated['period_month'] = $this->normalizePeriodMonth($validated['period_month']);

        $alreadyExists = EmployeeMobilityMonthlyComment::query()
            ->where('employee_id', $validated['employee_id'])
            ->where('period_month', $validated['period_month'])
            ->exists();

        if ($alreadyExists) {
            return $this->errorResponse('Ya existe un comentario mensual para ese empleado y mes.', 422);
        }

        $comment = EmployeeMobilityMonthlyComment::create($validated);

        return $this->successResponse($comment, 'Comentario mensual creado.', 201);
    }

    public function update(UpdateEmployeeMobilityMonthlyCommentRequest $request)
    {
        $validated = $request->validated();
        $validated['period_month'] = $this->normalizePeriodMonth($validated['period_month']);

        $comment = EmployeeMobilityMonthlyComment::query()
            ->where('employee_id', $validated['employee_id'])
            ->where('period_month', $validated['period_month'])
            ->first();

        if (! $comment) {
            return $this->errorResponse('Comentario mensual no encontrado.', 404);
        }

        $comment->update([
            'monthly_comment' => $validated['monthly_comment'],
        ]);

        return $this->successResponse($comment->fresh(), 'Comentario mensual actualizado.');
    }
}

