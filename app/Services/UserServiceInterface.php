<?php

namespace App\Services;

use App\DataTransferObjects\UserData;
use App\Http\Resources\UserResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

interface UserServiceInterface
{
    public function getAll(): array;

    public function getUsers(array $filters): AnonymousResourceCollection;

    public function getUsersOrderedByCheckInAndOut(
        array $filters
    ): AnonymousResourceCollection;

    public function create(UserData $dto): UserResource;

    public function get(int $id): UserResource;

    public function update(int $id, UserData $dto): UserResource;

    public function delete(int $id): void;

    public function restore(int $id): UserResource;
}
