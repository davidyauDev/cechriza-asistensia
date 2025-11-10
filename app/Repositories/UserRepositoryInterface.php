<?php

namespace App\Repositories;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use App\Models\User;

interface UserRepositoryInterface
{
    public function paginate(int $perPage = 10): LengthAwarePaginator;

    public function getFilteredUsers(array $filters): LengthAwarePaginator;

    public function create(array $data): User;

    public function find(int $id): ?User;

    public function update(User $user, array $data): User;

    public function delete(User $user): void;

    public function withTrashedFind(int $id): ?User;
}
