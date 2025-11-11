<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\DataTransferObjects\UserData;
use App\Models\User;
use App\Services\UserServiceInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class UserController extends Controller
{
    public function __construct(private UserServiceInterface $service)
    {
    }

    /**
     * Lista usuarios con paginación, búsqueda y ordenamiento
     */
    public function index(Request $request)
    {
        try {
            $filters = [
                'search' => $request->string('search')->trim(),
                'sort_by' => $this->getSortField($request->input('sort_by')),
                'sort_order' => $this->getSortOrder($request->input('sort_order')),
                'per_page' => $this->getPerPage($request->input('per_page'))
            ];

            $users = $this->service->getUsers($filters);

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
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener usuarios: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener todos los usuarios para filtros en frontend
     * Devuelve lista completa sin paginación (optimizado para dropdowns/filtros)
     */
    public function listAll()
    {
        try {
            $users = Cache::remember('all_users_simple', 3600, function () {
                return User::select('id', 'name')
                    ->orderBy('name', 'asc')
                    ->get();
            });

            return response()->json([
                'data' => $users,
                'total' => $users->count()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener usuarios: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Crear un nuevo usuario
     */

    public function store(StoreUserRequest $request)
    {
        try {
            $dto = UserData::fromArray($request->validated());
            $user = $this->service->create($dto);

            return (new UserResource($user))->response()->setStatusCode(201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al crear usuario: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mostrar un usuario específico
     */
    public function show(int $id)
    {
        try {
            $user = $this->service->get($id);
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuario no encontrado'
                ], 404);
            }
            return new UserResource($user);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener usuario: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Actualizar un usuario existente
     */
    public function update(UpdateUserRequest $request, int $id)
    {
        try {
            $dto = UserData::fromArray(array_merge(['id' => $id], $request->validated()));
            $updated = $this->service->update($id, $dto);
            if (!$updated) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuario no encontrado'
                ], 404);
            }

            return new UserResource($updated);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar usuario: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Eliminar un usuario
     */
    public function destroy(int $id)
    {
        try {
            $deleted = $this->service->delete($id);
            if (!$deleted) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuario no encontrado'
                ], 404);
            }
            
            return response()->json([
                'success' => true,
                'message' => 'Usuario eliminado exitosamente'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar usuario: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Restaurar un usuario eliminado
     */
    public function restore($id)
    {
        try {
            $restored = $this->service->restore((int) $id);
            if (!$restored) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuario no encontrado o no fue eliminado'
                ], 400);
            }

            return new UserResource($restored->fresh());
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al restaurar usuario: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Métodos helper privados para validar parámetros
     */
    private function getSortField(?string $sortBy): string
    {
        $allowedSorts = ['id', 'name', 'email', 'created_at'];
        return in_array($sortBy, $allowedSorts) ? $sortBy : 'id'; 
    }

    private function getSortOrder(?string $sortOrder): string
    {
        return $sortOrder === 'asc' ? 'asc' : 'desc';
    }

    private function getPerPage(?string $perPage): int
    {
        return max(1, min((int) $perPage ?: 10, 50));
    }
}
