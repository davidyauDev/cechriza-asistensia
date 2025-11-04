<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use OpenApi\Annotations as OA;

class AuthController extends Controller
{

    /**
     * @OA\Post(
     *     path="/api/login",
     *     tags={"Auth"},
     *     summary="Iniciar sesión",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"emp_code","password"},
     *             @OA\Property(property="emp_code", type="string", example="EMP001"),
     *             @OA\Property(property="password", type="string", example="password"),
     *             example={"emp_code":"EMP001","password":"password"}
     *         )
     *     ),
     *     @OA\Response(response=200, description="OK", @OA\JsonContent(
     *         required={"access_token","user"},
     *         @OA\Property(property="access_token", type="string"),
     *         @OA\Property(property="user", type="object", @OA\Property(property="id", type="integer"), @OA\Property(property="name", type="string"), @OA\Property(property="emp_code", type="string"))
     *     )),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'emp_code' => ['required', 'string'],
            'password' => ['required'],
        ]);

        $user = User::where('emp_code', $credentials['emp_code'])->first();

        if (!$user  || !Hash::check($credentials['password'], $user->password)) {
            return response()->json(['message' => 'The provided credentials are incorrect.'], 401);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'access_token' => $token,
            'user' => new UserResource($user),
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/logout",
     *     tags={"Auth"},
     *     summary="Cerrar sesión",
     *     description="Revoca el token del usuario autenticado",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Logout successful"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function logout()
    {
        // Note: actual logout logic should revoke tokens; this endpoint is documented here
        return response()->json(['message' => 'Logout successful'], 200);
    }
}
