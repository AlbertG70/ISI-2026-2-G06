<?php

namespace Tests\Feature;

use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * AuthTest — Pruebas de Integración de Autenticación (CU05)
 *
 * Verifica el flujo completo de autenticación:
 * - Registro de usuarios (email único, validación, hashing)
 * - Login exitoso y fallido
 * - Logout y revocación de token
 * - Protección de rutas autenticadas
 *
 * @package Tests\Feature
 */
class AuthTest extends TestCase
{
    use RefreshDatabase;

    // =========================================================================
    // CU05 — Registro
    // =========================================================================

    public function test_registro_exitoso_crea_usuario_y_retorna_token(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'email'                 => 'nuevo@dagaz.com',
            'password'              => 'Segura@1234',
            'password_confirmation' => 'Segura@1234',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'mensaje',
                'usuario' => ['id_usuario', 'email', 'rol'],
                'token',
                'tipo_token',
            ])
            ->assertJsonPath('usuario.rol', 'adulto')
            ->assertJsonPath('tipo_token', 'Bearer');

        $this->assertDatabaseHas('usuarios', ['email' => 'nuevo@dagaz.com', 'rol' => 'adulto']);
    }

    public function test_registro_falla_con_email_duplicado(): void
    {
        Usuario::create([
            'email'         => 'existente@dagaz.com',
            'password_hash' => Hash::make('pass1234'),
            'rol'           => 'adulto',
        ]);

        $response = $this->postJson('/api/v1/auth/register', [
            'email'                 => 'existente@dagaz.com',
            'password'              => 'Segura@1234',
            'password_confirmation' => 'Segura@1234',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_registro_falla_con_password_sin_confirmar(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'email'    => 'nuevo@dagaz.com',
            'password' => 'Segura@1234',
            // Sin password_confirmation
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    public function test_password_se_almacena_hasheado_no_en_texto_plano(): void
    {
        $this->postJson('/api/v1/auth/register', [
            'email'                 => 'hash@dagaz.com',
            'password'              => 'MiPassword123',
            'password_confirmation' => 'MiPassword123',
        ]);

        $usuario = Usuario::where('email', 'hash@dagaz.com')->first();
        $this->assertNotEquals('MiPassword123', $usuario->password_hash);
        $this->assertTrue(Hash::check('MiPassword123', $usuario->password_hash));
    }

    // =========================================================================
    // CU05 — Login
    // =========================================================================

    public function test_login_exitoso_retorna_token_bearer(): void
    {
        $usuario = Usuario::create([
            'email'         => 'login@dagaz.com',
            'password_hash' => Hash::make('MiPass123'),
            'rol'           => 'adulto',
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email'    => 'login@dagaz.com',
            'password' => 'MiPass123',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'mensaje',
                'usuario' => ['id_usuario', 'email', 'rol'],
                'token',
                'tipo_token',
            ])
            ->assertJsonPath('tipo_token', 'Bearer');
    }

    public function test_login_falla_con_password_incorrecta(): void
    {
        Usuario::create([
            'email'         => 'user@dagaz.com',
            'password_hash' => Hash::make('CorrectaPass'),
            'rol'           => 'adulto',
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email'    => 'user@dagaz.com',
            'password' => 'ContraseñaWrong',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_login_falla_con_email_no_registrado(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email'    => 'noexiste@dagaz.com',
            'password' => 'cualquierpass',
        ]);

        $response->assertStatus(422);
    }

    // =========================================================================
    // CU05 — Logout y Protección de Rutas
    // =========================================================================

    public function test_logout_revoca_token_correctamente(): void
    {
        $usuario = Usuario::create([
            'email'         => 'logout@dagaz.com',
            'password_hash' => Hash::make('LogoutPass'),
            'rol'           => 'adulto',
        ]);

        $token = $usuario->createToken('test-token')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/v1/auth/logout')
            ->assertStatus(200)
            ->assertJsonPath('mensaje', 'Sesión cerrada exitosamente.');

        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id' => $usuario->id_usuario,
        ]);
    }

    public function test_ruta_protegida_requiere_autenticacion(): void
    {
        $this->getJson('/api/v1/historial')
            ->assertStatus(401);

        $this->getJson('/api/v1/auth/perfil')
            ->assertStatus(401);
    }

    public function test_perfil_retorna_datos_del_usuario_autenticado(): void
    {
        $usuario = Usuario::create([
            'email'         => 'perfil@dagaz.com',
            'password_hash' => Hash::make('PerfilPass'),
            'rol'           => 'admin',
        ]);

        $token = $usuario->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/v1/auth/perfil')
            ->assertStatus(200)
            ->assertJsonPath('usuario.email', 'perfil@dagaz.com')
            ->assertJsonPath('usuario.rol', 'admin')
            ->assertJsonPath('usuario.es_administrador', true);
    }
}
