<?php

namespace App\Repositories;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;

class EloquentUserRepository implements UserRepositoryInterface
{
    public function paginate(int $perPage = 10): LengthAwarePaginator
    {
        return User::paginate($perPage);
    }

    public function getFilteredUsers(array $filters): LengthAwarePaginator
    {
        // $query = User::query();
        $query = User::query()->withTrashed();

        if (!empty($filters['search'])) {
            $search = trim($filters['search']);

            if (filter_var($search, FILTER_VALIDATE_EMAIL)) {
                $query->where('email', $search);
            } elseif (str_contains($search, '@')) {
                $query->where('email', 'like', $search . '%');
            } else {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', $search . '%')
                        ->orWhere('name', 'like', '% ' . $search . '%')
                        ->orWhere('email', 'like', $search . '%');
                });
            }
        }

        $sortBy = $filters['sort_by'] ?? 'id';
        $sortOrder = $filters['sort_order'] ?? 'desc';

        $query->select(['id', 'name', 'email', 'emp_code', 'role', 'created_at', 'active', 'updated_at', 'deleted_at']);

        $query->orderBy($sortBy, $sortOrder);

        $perPage = min($filters['per_page'] ?? 10, 50);

        return $query->paginate($perPage)->appends(request()->query());
    }

    //* Check in and Check out ordering
    // public function getUsersOrderedByCheckInAndOut(): Collection
    // {
    //     return User::with(['attendances' => function ($query) {
    //         $query->orderBy('type', 'asc');
    //     }])->get();
    // }


    public function getUsersOrderedByCheckInAndOut(array $filters): Collection
    {
        $user_id = $filters['user_id'] ?? null;
        $currentDate = $filters['date'] ?? date('Y-m-d');

        ds($currentDate);
       
        return User::whereHas('attendances', function ($query) use ($currentDate) {
            $query->whereDate('created_at', $currentDate);
        })
            ->with([
                'attendances' => function ($query) use ($currentDate) {
                    $query
                        ->select([
                            'id',
                            'user_id',
                            'client_id',
                            'timestamp',
                            'latitude',
                            'longitude',
                            'device_model',
                            'battery_percentage',
                            'signal_strength',
                            'network_type',
                            'type',
                            'address',
                            'created_at',
                        ])
                        ->whereDate('created_at', $currentDate)
                        ->orderByRaw("
                        CASE 
                            WHEN type = 'check_in' THEN 1
                            WHEN type = 'check_out' THEN 2
                            ELSE 3
                        END
                    ")
                        ->orderBy('created_at', 'asc');
                }
            ])
            ->when($user_id, function ($query) use ($user_id) {
                $query->where('id', $user_id);
            })
            ->get();
    }




    public function getUsersNotCheckedOut(): Collection
    {

        // Lógica para obtener usuarios que no han registrado su salida
        $users = User::whereDoesntHave('attendances', function ($query) {
            // $query->whereDate('created_at', date('Y-m-d'));
            $query->where('type', 'check_out')->whereDate('created_at', date('Y-m-d'));
        })->get();

        return $users;
    }


    public function getUsersNotCheckedInOutByCurrentDate(): Collection
    {
        $today = date('Y-m-d');
        $users = User::with([
            'attendances' => function ($query) use ($today) {
                $query->whereDate('created_at', $today)->select([
                    'id',
                    'user_id',
                    'type',
                ]);
            }
        ])->withCount([
                    'attendances as attendances_today_count' => function ($query) use ($today) {
                        $query->whereDate('created_at', $today);
                    }
                ])
            ->having('attendances_today_count', '<', 2)
            ->orderBy('created_at', 'desc')
            ->get();

        return $users;
    }

    public function create(array $data): User
    {
        return User::create($data);
    }

    public function find(int $id): ?User
    {
        return User::find($id);
    }

    public function findByEmail(string $email): ?User
    {
        return User::where('email', $email)->first();
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


// roles
// SUPER_ADMIN
// ADMIN
// TECHNICIAN