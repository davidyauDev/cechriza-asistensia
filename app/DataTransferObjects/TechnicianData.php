<?php

namespace App\DataTransferObjects;

class TechnicianData
{
    public function __construct(
        public readonly ?int $id,
        public readonly ?string $code,
        public readonly ?string $name,
        public readonly ?string $email,
        public readonly ?string $phone,
        public readonly ?string $department,
        public readonly ?string $position,
        public readonly ?string $status,
        public readonly ?array $routes = [],
    ) {
    }

    /**
     * Crear DTO desde un array
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'] ?? null,
            code: $data['code'] ?? null,
            name: $data['name'] ?? null,
            email: $data['email'] ?? null,
            phone: $data['phone'] ?? null,
            department: $data['department'] ?? null,
            position: $data['position'] ?? null,
            status: $data['status'] ?? 'active',
            routes: $data['routes'] ?? [],
        );
    }

    /**
     * Convertir DTO a array
     */
    public function toArray(): array
    {
        return array_filter([
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'department' => $this->department,
            'position' => $this->position,
            'status' => $this->status,
            'routes' => $this->routes,
        ], fn($value) => $value !== null);
    }
}
