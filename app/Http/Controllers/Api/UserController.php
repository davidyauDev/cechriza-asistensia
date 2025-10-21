<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\DataTransferObjects\UserData;
use App\Services\UserServiceInterface;
use Illuminate\Http\Request;
use OpenApi\Annotations as OA;

/**
 * @OA\Schema(
 *   schema="User",
 *   @OA\Property(property="id", type="integer", example=1),
 *   @OA\Property(property="name", type="string", example="Juan Perez"),
 *   @OA\Property(property="email", type="string", format="email", example="juan@example.com")
 * )
 *
 * @OA\Schema(
 *   schema="UserCollection",
 *   type="array",
 *   @OA\Items(ref="#/components/schemas/User")
 * )
 */

class UserController extends Controller
{
    public function __construct(private UserServiceInterface $service)
    {
    }

    public function index(Request $request)
    {
        $perPage = min((int) $request->input('per_page', 10), 100);
        $users  = $this->service->list($perPage);
        return UserResource::collection($users);
    }

    /**
     * @OA\Get(
     *     path="/api/users",
     *     tags={"Users"},
     *     summary="List users",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="per_page", in="query", @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="OK", @OA\JsonContent(type="array", @OA\Items(
     *         type="object",
     *         @OA\Property(property="id", type="integer"),
     *         @OA\Property(property="name", type="string"),
     *         @OA\Property(property="email", type="string", format="email")
     *     )))
     * )
     */


    public function store(StoreUserRequest $request)
    {
        $dto = UserData::fromArray($request->validated());
        $user = $this->service->create($dto);

        return (new UserResource($user))->response()->setStatusCode(201);
    }

    /**
     * @OA\Post(
     *     path="/api/users",
     *     tags={"Users"},
     *     summary="Create user",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(@OA\JsonContent(required={"name","email","password"},
     *         @OA\Property(property="name", type="string"),
     *         @OA\Property(property="email", type="string", format="email"),
     *         @OA\Property(property="password", type="string")
     *     )),
     *     @OA\Response(response=201, description="Created", @OA\JsonContent(@OA\Property(property="id", type="integer")))
     * )
     */

    public function show(int $id)
    {
        $user = $this->service->get($id);
        if (!$user) {
            return response()->json(['message' => 'User not found.'], 404);
        }
        return new UserResource($user);
    }

    /**
     * @OA\Get(
     *     path="/api/users/{user}",
     *     tags={"Users"},
     *     summary="Get user",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="user", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="OK", @OA\JsonContent(
     *         type="object",
     *         @OA\Property(property="id", type="integer"),
     *         @OA\Property(property="name", type="string"),
     *         @OA\Property(property="email", type="string", format="email")
     *     ))
     * )
     */

    public function update(UpdateUserRequest $request, int $id)
    {
        $dto = UserData::fromArray(array_merge(['id' => $id], $request->validated()));
        $updated = $this->service->update($id, $dto);
        if (!$updated) {
            return response()->json(['message' => 'User not found.'], 404);
        }

        return new UserResource($updated);
    }

    /**
     * @OA\Put(
     *     path="/api/users/{user}",
     *     tags={"Users"},
     *     summary="Update user",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="user", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(@OA\JsonContent(
     *         @OA\Property(property="name", type="string"),
     *         @OA\Property(property="email", type="string", format="email"),
     *         @OA\Property(property="password", type="string")
     *     )),
     *     @OA\Response(response=200, description="OK")
     * )
     */

    public function destroy(int $id)
    {
        $this->service->delete($id);
        return response()->json(null, 204);
    }

    /**
     * @OA\Delete(
     *     path="/api/users/{user}",
     *     tags={"Users"},
     *     summary="Delete user",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="user", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=204, description="No Content")
     * )
     */

    public function restore($id)
    {
        $restored = $this->service->restore((int) $id);
        if (!$restored) {
            return response()->json(['message' => 'User not found or not deleted.'], 400);
        }

        return new UserResource($restored->fresh());
    }

    /**
     * @OA\Post(
     *     path="/api/users/{id}/restore",
     *     tags={"Users"},
     *     summary="Restore deleted user",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="OK"),
     *     @OA\Response(response=400, description="Bad Request")
     * )
     */
}
