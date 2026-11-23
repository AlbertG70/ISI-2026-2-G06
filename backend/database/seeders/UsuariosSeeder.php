<?php

namespace Database\Seeders;

use App\Models\Usuario;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * UsuariosSeeder — Usuarios de prueba DAGAZ
 *
 * Crea usuarios de prueba en la tabla `usuarios` de raiden.
 * Usa `updateOrCreate` por email para ser idempotente.
 *
 * Nota: el rol por defecto en la BD es 'lector', pero DAGAZ
 * usa 'adulto' y 'admin' como roles principales del sistema.
 */
class UsuariosSeeder extends Seeder
{
    public function run(): void
    {
        $usuarios = [
            [
                'email'         => 'admin@dagaz.com',
                'password_hash' => Hash::make('admin1234'),
                'rol'           => 'admin',
            ],
            [
                'email'         => 'adulto@dagaz.com',
                'password_hash' => Hash::make('adulto1234'),
                'rol'           => 'adulto',
            ],
            [
                'email'         => 'editor@dagaz.com',
                'password_hash' => Hash::make('editor1234'),
                'rol'           => 'editor',
            ],
        ];

        foreach ($usuarios as $datos) {
            $usuario = Usuario::updateOrCreate(
                ['email' => $datos['email']],
                [
                    'password_hash' => $datos['password_hash'],
                    'rol'           => $datos['rol'],
                ]
            );

            $estado = $usuario->wasRecentlyCreated ? 'creado' : 'actualizado';
            $this->command->info("Usuario {$datos['email']} ({$datos['rol']}) — {$estado}.");
        }
    }
}
