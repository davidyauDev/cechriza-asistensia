<?php

namespace App\Repositories;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class EloquentUserRepository implements UserRepositoryInterface
{
    public function paginate(int $perPage = 10): LengthAwarePaginator
    {
        return User::paginate($perPage);
    }

    public function getFilteredUsers(array $filters): LengthAwarePaginator
    {
        $query = User::query();

        if (!empty($filters['search'])) {
            $search = trim($filters['search']);
            
            if (filter_var($search, FILTER_VALIDATE_EMAIL)) {
                $query->where('email', $search);
            } 
            elseif (str_contains($search, '@')) {
                $query->where('email', 'like', $search . '%');
            }
            else {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', $search . '%') 
                      ->orWhere('name', 'like', '% ' . $search . '%') 
                      ->orWhere('email', 'like', $search . '%');
                });
            }
        }

        $sortBy = $filters['sort_by'] ?? 'id';
        $sortOrder = $filters['sort_order'] ?? 'desc';
        
        $query->select(['id', 'name', 'email', 'created_at', 'updated_at']);
        
        $query->orderBy($sortBy, $sortOrder);

        $perPage = min($filters['per_page'] ?? 10, 50); 
        
        return $query->paginate($perPage)->appends(request()->query());
    }

    public function create(array $data): User
    {
        return User::create($data);
    }

    public function find(int $id): ?User
    {
        return User::find($id);
    }

    public function update(User $user, array $data): User
    {
        $user->update($data);
        return $user;
    }

    public function delete(User $user): void
    {
        $user->delete();
    }

    public function withTrashedFind(int $id): ?User
    {
        return User::withTrashed()->find($id);
    }
}
