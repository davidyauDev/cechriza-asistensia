<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\DataTransferObjects\UserData;
use App\Services\UserServiceInterface;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;



class UserController extends Controller
{

    use ApiResponseTrait;

    public function __construct(private UserServiceInterface $service)
    {
    }


    /**
     * Lista usuarios con paginación, búsqueda y ordenamiento
     */
    public function index(Request $request)
    {
        // try {
        $filters = [
            'search' => $request->string('search')->trim(),
            'sort_by' => $this->getSortField($request->input('sort_by')),
            'sort_order' => $this->getSortOrder($request->input('sort_order')),
            'per_page' => $this->getPerPage($request->input('per_page'))
        ];

        return $this->successResponse(
            $this->service->getUsers($filters),
            'Users retrieved successfully'
        );


    }

    /**
     * Obtener todos los usuarios para filtros en frontend
     * Devuelve lista completa sin paginación (optimizado para dropdowns/filtros)
     */
    public function listAll()
    {
        return $this->successResponse(
            $this->service->getAll(),
            'All users retrieved successfully'
        );

    }


    public function listByCheckInAndOut(Request $request)
    {
        $filters = [
            'user_id' => $request->input('user_id'),
        ];

        return $this->successResponse(
            $this->service->getUsersOrderedByCheckInAndOut($filters),
            'Users with attendances retrieved successfully'
        );

    }

    /**
     * Crear un nuevo usuario
     */

    public function store(StoreUserRequest $request)
    {
        // try {
        $dto = UserData::fromArray($request->validated());
        // $user = $this->service->create($dto);
        return $this->successResponse(
            $this->service->create($dto),
            'User created successfully'
        );


    }

    /**
     * Mostrar un usuario específico
     */
    public function show(int $id)
    {

        return $this->successResponse(
            $this->service->get($id),
            'User retrieved successfully'
        );

    }

    /**
     * Actualizar un usuario existente
     */
    public function update(UpdateUserRequest $request, int $id)
    {


        $dto = UserData::fromArray(array_merge(['id' => $id], $request->validated()));
        return $this->successResponse(
            $this->service->update($id, $dto),
            'User updated successfully'
        );
    }

    /**
     * Eliminar un usuario
     */
    public function destroy(int $id)
    {

        return $this->successResponse(
            $this->service->delete($id),
            'User deleted successfully'
        );


    }

    /**
     * Restaurar un usuario eliminado
     */
    public function restore($id)
    {

        return $this->successResponse(
            $this->service->restore(intval($id)),
            'User restored successfully'
        );
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
