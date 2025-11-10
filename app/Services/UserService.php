<?php

namespace App\Services;

use App\DataTransferObjects\UserData;
use App\Repositories\UserRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use App\Models\User;

class UserService implements UserServiceInterface
{
    public function __construct(private UserRepositoryInterface $repository)
    {
    }

    public function list(int $perPage = 10): LengthAwarePaginator
    {
        return $this->repository->paginate($perPage);
    }

    public function getUsers(array $filters): LengthAwarePaginator
    {
        return $this->repository->getFilteredUsers($filters);
    }

    public function create(UserData $dto): User
    {
        $data = $dto->toArray();
        if (isset($data['password']) && !empty($data['password'])) {
            $data['password'] = bcrypt($data['password']);
        }

        return $this->repository->create($data);
    }

    public function get(int $id): ?User
    {
        return $this->repository->find($id);
    }

    public function update(int $id, UserData $dto): ?User
    {
        $user = $this->repository->find($id);
        if (!$user) {
            return null;
        }

        $data = $dto->toArray();

        if (array_key_exists('password', $data)) {
            if (!empty($data['password'])) {
                $data['password'] = bcrypt($data['password']);
            } else {
                unset($data['password']);
            }
        }

        return $this->repository->update($user, $data);
    }

    public function delete(int $id): bool
    {
        $user = $this->repository->find($id);
        if ($user) {
            $this->repository->delete($user);
            return true;
        }
        return false;
    }

    public function restore(int $id): ?User
    {
        $user = $this->repository->withTrashedFind($id);
        if (!$user) {
            return null;
        }

        if ($user->trashed()) {
            $user->restore();
            return $user;
        }

        return null;
    }
}
