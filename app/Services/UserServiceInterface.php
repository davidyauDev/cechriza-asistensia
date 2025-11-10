<?php

namespace App\Services;

use App\DataTransferObjects\UserData;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use App\Models\User;

interface UserServiceInterface
{
    public function list(int $perPage = 10): LengthAwarePaginator;

    public function getUsers(array $filters): LengthAwarePaginator;

    public function create(UserData $dto): User;

    public function get(int $id): ?User;

    public function update(int $id, UserData $dto): ?User;

    public function delete(int $id): bool;

    public function restore(int $id): ?User;
}
