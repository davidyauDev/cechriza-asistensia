<?php


namespace App\Services;

use App\Http\Requests\LoginRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\AuthServiceInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Hash;


class AuthService implements AuthServiceInterface
{
    public function login(LoginRequest $request): array|null
    {
        $user = User::where('emp_code', $request->emp_code)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw new AuthorizationException('The provided credentials are incorrect.');
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return [
            'access_token' => $token,
            'user' => new UserResource($user),
        ];
    }

    public function logout(): void
    {
        // Assuming we have access to the currently authenticated user
        $user = auth()->user();
        if ($user) {
            $user->currentAccessToken()->delete();
        }
    }
}
