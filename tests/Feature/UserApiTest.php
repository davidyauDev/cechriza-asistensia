<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

uses(\Tests\TestCase::class, \Illuminate\Foundation\Testing\RefreshDatabase::class);


beforeEach(function () {
    $this->actingAs(User::factory()->create());
});


function validUserData(array $overrides = []): array
{
    return array_merge([
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password123',
    ], $overrides);
}


test('el endpoint index devuelve una lista paginada de usuarios con el formato correcto', function () {
    User::factory()->count(15)->create();
    $this->getJson('/api/users?per_page=5')
         ->assertStatus(200) 
         ->assertJsonStructure(['data', 'links', 'meta'])
         ->assertJsonCount(5, 'data');
});

test('el endpoint show devuelve un único usuario', function () {
    $userToView = User::factory()->create();
    $this->getJson("/api/users/{$userToView->id}")
         ->assertStatus(200)
         ->assertJsonPath('data.id', $userToView->id);
});


test('el endpoint store crea un usuario y hashea la contraseña correctamente', function () {
    $userData = validUserData();
    $response = $this->postJson('/api/users', $userData);
    $response->assertStatus(201) 
             ->assertJsonPath('data.email', $userData['email']);
    $this->assertDatabaseHas('users', ['email' => $userData['email']]);
    $user = User::where('email', $userData['email'])->first();
    $this->assertTrue(Hash::check($userData['password'], $user->password));
});


test('el endpoint update actualiza el email del usuario', function () {
    $user = User::factory()->create(['email' => 'old@mail.com']);
    $newEmail = 'new@mail.com';
    $this->putJson("/api/users/{$user->id}", ['email' => $newEmail])
         ->assertStatus(200)
         ->assertJsonPath('data.email', $newEmail);
    $this->assertDatabaseHas('users', ['id' => $user->id, 'email' => $newEmail]);
});

test('el endpoint update permite actualizar la contraseña sin cambiar otros campos', function () {
    $oldPassword = 'old-secret';
    $newPassword = 'new-secret';
    $user = User::factory()->create(['password' => Hash::make($oldPassword)]);
    $oldName = $user->name;
    $this->putJson("/api/users/{$user->id}", ['password' => $newPassword])
         ->assertStatus(200)
         ->assertJsonPath('data.name', $oldName); 
    $user->refresh();
    $this->assertTrue(Hash::check($newPassword, $user->password));
    $this->assertFalse(Hash::check($oldPassword, $user->password));
});


test('el endpoint destroy realiza un soft delete y restore funciona correctamente', function () {
    $user = User::factory()->create();
    $this->deleteJson("/api/users/{$user->id}")
         ->assertStatus(204); 
    $this->assertSoftDeleted($user);
    $this->postJson("/api/users/{$user->id}/restore")
         ->assertStatus(200)
         ->assertJsonPath('data.id', $user->id);
    $this->assertNotSoftDeleted($user);
});

test('el endpoint restore devuelve 400 si el usuario no está eliminado', function () {
    $user = User::factory()->create();
    $this->postJson("/api/users/{$user->id}/restore")
         ->assertStatus(400)
         ->assertJson(['message' => 'User is not deleted.']);
});