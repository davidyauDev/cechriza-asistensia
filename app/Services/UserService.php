<?php

namespace App\Services;

use App\DataTransferObjects\UserData;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Repositories\UserRepositoryInterface;
use Cache;
use App\Models\User;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class UserService implements UserServiceInterface
{
    public function __construct(private UserRepositoryInterface $repository)
    {
    }

    public function getAll(): array
    {
        // Cache::forget('all_users_simple');
        $users = Cache::remember('all_users_simple', 3600, function () {
            return User::select('id', 'name', 'emp_code')
                ->orderBy('name', 'asc')
                ->get();
        });

        ds($users->toArray());

        return [
            'users' => $users,
            'total' => $users->count()
        ];
    }
    public function getUsers(array $filters): AnonymousResourceCollection
    {
        $users = $this->repository->getFilteredUsers($filters);

        return UserResource::collection($users)
            ->additional([
                'meta' => [
                    'filters' => $filters,
                    'pagination' => [
                        'total' => $users->total(),
                        'per_page' => $users->perPage(),
                        'current_page' => $users->currentPage(),
                        'last_page' => $users->lastPage(),
                    ]
                ]
            ]);
    }

    public function getUsersOrderedByCheckInAndOut(
        array $filters
    ): AnonymousResourceCollection
    {
        $user_id = $filters['user_id'] ?? null;
        if ($user_id) {
            User::find($user_id) ?? throw new NotFoundHttpException('El usuario no existe');
        }

        $users = $this->repository->getUsersOrderedByCheckInAndOut(
            $filters
        );

        return UserResource::collection($users);
    }

    public function getUsersNotCheckedOut(): AnonymousResourceCollection
    {
        $users = $this->repository->getUsersNotCheckedOut();

        return UserResource::collection($users);
    }

    public function create(StoreUserRequest $dto): UserResource
    {
        $data = $dto->toArray();
        if (isset($data['password']) && !empty($data['password'])) {
            $data['password'] = bcrypt($data['password']);
        }

        ds($data);

        $user = $this->repository->create($data);

        Cache::forget('all_users_simple');

        return new UserResource($user);
    }

    public function get(int $id): UserResource
    {
        $user = $this->repository->find($id);
        if (!$user) {
            throw new NotFoundHttpException('User not found');
        }
        ;
        return new UserResource($user);
    }

    public function update(int $id, UpdateUserRequest $dto): UserResource
    {
        $user = $this->repository->find($id);
        if (!$user)
            throw new NotFoundHttpException('User not found');

        $data = $dto->toArray();

        if (array_key_exists('password', $data)) {
            if (!empty($data['password'])) {
                $data['password'] = bcrypt($data['password']);
            } else {
                unset($data['password']);
            }
        }

        $user = $this->repository->update($user, $data);
        return new UserResource($user);
    }


    public function delete(int $id): void
    {
        $user = $this->repository->find($id);
        if (!$user) throw new NotFoundHttpException('User not found');

        $this->repository->delete($user);
    }

    public function toggleActiveStatus(int $id): void
    {
        $user = $this->repository->find($id);
        if (!$user) {
            throw new NotFoundHttpException('User not found');
        }

        $user->active = !$user->active;
        $user->save();

       
    }

   

    public function restore(int $id): UserResource
    {
        $user = $this->repository->withTrashedFind($id);
        if (!$user) {
            // return null;
            throw new NotFoundHttpException('Usuario no encontrado o no fue eliminado');
        }

        if ($user->trashed()) {
            $user->restore();
            return new UserResource($user->fresh());
        }

        throw new NotFoundHttpException('Usuario no encontrado o no fue eliminado');
    }
}
