<?php


namespace App\Services;

use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AuthService
{
    public function login(string $empCode, string $password): array|null
    {
        $user = User::where('emp_code', $empCode)->first();

        if (!$user || !Hash::check($password, $user->password)) {
            return null;
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return [
            'access_token' => $token,
            'user' => new UserResource($user),
        ];
    }
}
