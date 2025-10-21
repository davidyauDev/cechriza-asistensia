<?php

namespace App\DataTransferObjects;

class UserData
{
    public function __construct(
        public ?int $id,
        public ?string $name,
        public ?string $email,
        public ?string $password
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            $data['id'] ?? null,
            $data['name'] ?? null,
            $data['email'] ?? null,
            $data['password'] ?? null,
        );
    }

    public function toArray(): array
    {
        return array_filter([
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'password' => $this->password,
        ], function ($v) {
            return $v !== null;
        });
    }
}
