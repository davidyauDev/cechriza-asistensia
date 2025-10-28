<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class UsersFromJsonSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Ruta al archivo JSON
        $jsonPath = database_path('data/users.json');
        
        if (!file_exists($jsonPath)) {
            $this->command->error("El archivo JSON no existe en: {$jsonPath}");
            return;
        }

        // Leer el archivo JSON
        $jsonContent = file_get_contents($jsonPath);
        $data = json_decode($jsonContent, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->command->error("Error al parsear el JSON: " . json_last_error_msg());
            return;
        }

        // Obtener el array de usuarios
        $users = $data['select * from personnel_employee '] ?? [];

        if (empty($users)) {
            $this->command->error("No se encontraron usuarios en el JSON");
            return;
        }

        $this->command->info("Procesando " . count($users) . " usuarios...");

        $createdCount = 0;
        $skippedCount = 0;

        // Deshabilitar temporalmente los auto incrementos para permitir IDs específicos
        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        foreach ($users as $userData) {
            try {
                // Verificar si el usuario ya existe
                $existingUser = User::where('id', $userData['id'])->first();
                
                if ($existingUser) {
                    $this->command->warn("Usuario con ID {$userData['id']} ya existe, saltando...");
                    $skippedCount++;
                    continue;
                }

                // Crear el nombre completo
                $fullName = trim(($userData['first_name'] ?? '') . ' ' . ($userData['last_name'] ?? ''));
                
                // Generar email si no existe
                $email = $userData['email'] ?? 
                         (!empty($userData['emp_code']) ? 
                          strtolower($userData['emp_code']) . '@cechriza.com' : 
                          "user{$userData['id']}@cechriza.com");

                // Insertar usuario con ID específico
                DB::table('users')->insert([
                    'id' => $userData['id'],
                    'emp_code' => $userData['emp_code'] ?? null,
                    'first_name' => $userData['first_name'] ?? null,
                    'last_name' => $userData['last_name'] ?? null,
                    'name' => $fullName ?: "Usuario {$userData['id']}",
                    'email' => $email,
                    'password' => Hash::make('password123'), // Password por defecto
                    'email_verified_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $this->command->info("Usuario creado: ID {$userData['id']} - {$fullName}");
                $createdCount++;

            } catch (\Exception $e) {
                $this->command->error("Error al crear usuario ID {$userData['id']}: " . $e->getMessage());
                Log::error("Error creating user", [
                    'user_id' => $userData['id'],
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
        }

        // Reactivar foreign key checks
        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        // Ajustar el auto_increment para que sea mayor que el último ID insertado
        if ($createdCount > 0) {
            $maxId = collect($users)->max('id');
            DB::statement("ALTER TABLE users AUTO_INCREMENT = " . ($maxId + 1));
        }

        $this->command->info("Proceso completado:");
        $this->command->info("- Usuarios creados: {$createdCount}");
        $this->command->info("- Usuarios saltados: {$skippedCount}");
    }
}
