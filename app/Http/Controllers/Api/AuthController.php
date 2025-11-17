<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Services\AuthServiceInterface;
use App\Traits\ApiResponseTrait;


class AuthController extends Controller
{

    use ApiResponseTrait;
    
    public function __construct(private AuthServiceInterface $authService)
    {
    }

    public function login(LoginRequest $request)
    {
        return $this->successResponse($this->authService->login($request), 'Login successful');
    }

    public function logout()
    {
        return $this->successResponse( $this->authService->logout(), 'Logout successful');
    }
}
