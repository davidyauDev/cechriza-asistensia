<?php

namespace App\Services;

interface EmployeeConceptServiceInterface
{
    public function storeConcept(
        int $employeeId,
        string $empCode,
        int $conceptId,
        string $startDate,
        string $endDate,
        ?string $comment = null
    ): array;
}
