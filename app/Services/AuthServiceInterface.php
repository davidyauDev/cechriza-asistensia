<?php

namespace App\Services;

use App\Http\Requests\LoginRequest;
interface AuthServiceInterface
{
    public function login(LoginRequest $request): array | null;
    public function logout(): void;
}