<?php

namespace App\Services;

interface EmployeeBirthdayServiceInterface
{
    public function getBirthdaysByMonth(int $month, ?string $search = null): array;
}
