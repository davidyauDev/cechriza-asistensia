<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Evento;
use App\Models\ImagenEvento;

class EventoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Crear evento "Día del Padre" como en el ejemplo
        $eventoPadre = Evento::create([
            'titulo' => 'Día del Padre',
            'descripcion' => 'Celebrando a todos los papás 👨‍👧‍👦',
            'fecha_inicio' => '2025-06-15',
            'fecha_fin' => '2025-06-17',
            'estado' => 'programado'
        ]);

        // Agregar imágenes al evento del Día del Padre
        $imagenesPadre = [
            [
                'url_imagen' => 'https://miapp.com/uploads/padre1.jpg',
                'descripcion' => 'Papá programando 💻',
                'orden' => 1,
                'autor' => null
            ],
            [
                'url_imagen' => 'https://miapp.com/uploads/padre2.jpg',
                'descripcion' => 'Papá y su hijo celebrando juntos 🎉',
                'orden' => 2,
                'autor' => null
            ],
            [
                'url_imagen' => 'https://miapp.com/uploads/padre3.jpg',
                'descripcion' => 'Feliz Día del Padre ❤️',
                'orden' => 3,
                'autor' => null
            ]
        ];

        foreach ($imagenesPadre as $imagenData) {
            ImagenEvento::create([
                'evento_id' => $eventoPadre->id,
                'url_imagen' => $imagenData['url_imagen'],
                'descripcion' => $imagenData['descripcion'],
                'orden' => $imagenData['orden'],
                'autor' => $imagenData['autor'],
            ]);
        }

        // Crear otro evento de ejemplo
        $eventoMadre = Evento::create([
            'titulo' => 'Día de la Madre',
            'descripcion' => 'Celebrando a todas las mamás especiales 👩‍👧‍👦💐',
            'fecha_inicio' => '2025-05-10',
            'fecha_fin' => '2025-05-12',
            'estado' => 'activo'
        ]);

        // Agregar imágenes al evento del Día de la Madre
        $imagenesMadre = [
            [
                'url_imagen' => 'https://miapp.com/uploads/madre1.jpg',
                'descripcion' => 'Mamá trabajando desde casa 🏠💻',
                'orden' => 1,
                'autor' => 'Admin'
            ],
            [
                'url_imagen' => 'https://miapp.com/uploads/madre2.jpg',
                'descripcion' => 'Flores para mamá 🌹',
                'orden' => 2,
                'autor' => 'Admin'
            ]
        ];

        foreach ($imagenesMadre as $imagenData) {
            ImagenEvento::create([
                'evento_id' => $eventoMadre->id,
                'url_imagen' => $imagenData['url_imagen'],
                'descripcion' => $imagenData['descripcion'],
                'orden' => $imagenData['orden'],
                'autor' => $imagenData['autor'],
            ]);
        }

        // Crear evento finalizado
        $eventoNavidad = Evento::create([
            'titulo' => 'Navidad 2024',
            'descripcion' => 'Celebración navideña de fin de año 🎄🎅',
            'fecha_inicio' => '2024-12-20',
            'fecha_fin' => '2024-12-26',
            'estado' => 'finalizado'
        ]);

        ImagenEvento::create([
            'evento_id' => $eventoNavidad->id,
            'url_imagen' => 'https://miapp.com/uploads/navidad1.jpg',
            'descripcion' => 'Árbol de navidad del equipo 🎄',
            'orden' => 1,
            'autor' => 'Recursos Humanos',
        ]);
    }
}
