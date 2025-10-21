<?php

namespace Tests\Feature\Http\Controllers\Api;

use Tests\TestCase;
use App\Models\User;
use App\Services\UserServiceInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;

class UserControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_delegates_to_service()
    {
        $mock = $this->createMock(UserServiceInterface::class);
            $paginator = new LengthAwarePaginator([], 0, 10);

            $mock->expects($this->once())
                ->method('list')
                ->with(10)
                ->willReturn($paginator);

        $this->app->instance(UserServiceInterface::class, $mock);

        $this->getJson('/api/users')
            ->assertStatus(200);
    }
}
