<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Services\UserService;
use App\Repositories\UserRepositoryInterface;
use App\DataTransferObjects\UserData;
use App\Models\User;

class UserServiceTest extends TestCase
{
    public function test_create_hashes_password_and_calls_repository()
    {
        $repo = $this->createMock(UserRepositoryInterface::class);

        $input = ['name' => 'Test', 'email' => 't@example.com', 'password' => 'secret123'];
        $dto = UserData::fromArray($input);

        $repo->expects($this->once())
            ->method('create')
            ->with($this->callback(function ($data) use ($input) {
                // password must be present, be a string and not equal to plain text
                return isset($data['password']) && is_string($data['password']) && $data['password'] !== $input['password'];
            }))
            ->willReturn(new User(array_merge($input, ['id' => 1])));

        $service = new UserService($repo);
        $user = $service->create($dto);

        $this->assertInstanceOf(User::class, $user);
    }
}
