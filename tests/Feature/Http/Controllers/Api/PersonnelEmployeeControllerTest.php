<?php

namespace Tests\Feature\Http\Controllers\Api;

use App\Models\User;
use App\Services\EmployeeBirthdayServiceInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PersonnelEmployeeControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_birthdays_by_month_returns_frontend_ready_maps(): void
    {
        $user = User::factory()->create();

        $mock = $this->createMock(EmployeeBirthdayServiceInterface::class);
        $mock->expects($this->once())
            ->method('getBirthdaysByMonth')
            ->with(4, null)
            ->willReturn([
                'success' => true,
                'month' => 4,
                'month_label' => 'Abril',
                'total_birthdays' => 1,
                'birthdays' => [
                    [
                        'id' => 15,
                        'employee_id' => 15,
                        'dni' => '12345678',
                        'first_name' => 'Juan',
                        'last_name' => 'Perez',
                        'full_name' => 'Juan Perez',
                        'birthday' => '1990-04-15',
                        'birthday_day' => '15',
                        'birthday_month' => 4,
                        'birthday_year' => 1990,
                        'department_name' => 'Tecnicos',
                        'position_name' => 'Supervisor',
                    ],
                ],
                'by_day' => ['15' => []],
                'by_department' => ['Tecnicos' => []],
                'notifications' => [
                    [
                        'id' => 15,
                        'employee_id' => 15,
                        'dni' => '12345678',
                        'title' => 'Cumpleaños del mes',
                        'message' => 'Juan Perez cumple el 15 de Abril.',
                        'selected' => true,
                        'type' => 'birthday',
                    ],
                ],
                'selected_users' => [],
            ]);

        $this->app->instance(EmployeeBirthdayServiceInterface::class, $mock);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/biotime/personnel-employees/birthdays-by-month?month=4')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('month', 4)
            ->assertJsonPath('total_birthdays', 1)
            ->assertJsonPath('notifications.0.selected', true);
    }
}
