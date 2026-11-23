<?php

namespace App\Http\Controllers;

use App\Models\Usuario;
use App\Models\Historial;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * AuthController — Controlador de Autenticación y Gestión de Credenciales
 *
 * Implementa CU05: Inicio de Sesión y Autenticación.
 * Gestiona el acceso seguro al sistema mediante validación de credenciales,
 * generación de tokens Sanctum y control de roles (adulto, admin, editor).
 *
 * @see \App\Models\Usuario
 * @package App\Http\Controllers
 */
class AuthController extends Controller
{
    /**
     * Registrar un nuevo usuario en el sistema.
     *
     * CU05 — Flujo de Registro de Credenciales.
     * Valida la unicidad del email, hashea la contraseña con Bcrypt (cost 12)
     * o Argon2id según configuración, y crea el usuario con rol predeterminado 'adulto'.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email'    => ['required', 'email', 'max:255', 'unique:usuarios,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'rol'      => ['sometimes', 'string', 'in:lector,adulto,editor'],
        ]);

        try {
            $usuario = Usuario::create([
                'email'         => $validated['email'],
                'password_hash' => Hash::make($validated['password']),
                'rol'           => $validated['rol'] ?? 'adulto',
            ]);

            // Generar token Sanctum para la sesión inicial
            $token = $usuario->createToken('dagaz-auth-token')->plainTextToken;

            Log::info('[DAGAZ][Auth] Usuario registrado.', [
                'id_usuario' => $usuario->id_usuario,
                'email'      => $usuario->email,
                'rol'        => $usuario->rol,
            ]);

            return response()->json([
                'mensaje'  => 'Usuario registrado exitosamente.',
                'usuario'  => [
                    'id_usuario' => $usuario->id_usuario,
                    'email'      => $usuario->email,
                    'rol'        => $usuario->rol,
                ],
                'token'    => $token,
                'tipo_token' => 'Bearer',
            ], 201);

        } catch (\Throwable $e) {
            Log::error('[DAGAZ][Auth] Error al registrar usuario.', [
                'email' => $validated['email'],
                'error' => $e->getMessage(),
            ]);
            return response()->json([
                'mensaje' => 'Error interno al registrar el usuario. Intente nuevamente.',
            ], 500);
        }
    }

    /**
     * Iniciar sesión y obtener token de autenticación.
     *
     * CU05 — Flujo Básico: Validar Credenciales.
     * Contrasta email y contraseña contra la tabla `usuarios`.
     * Si es exitoso, genera un token Sanctum y registra el evento en historial.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     * @throws \Illuminate\Validation\ValidationException  FA1: Credenciales incorrectas.
     */
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        /** @var Usuario|null $usuario */
        $usuario = Usuario::where('email', $validated['email'])->first();

        // FA1: Email no encontrado o contraseña incorrecta
        if (! $usuario || ! Hash::check($validated['password'], $usuario->password_hash)) {
            Log::warning('[DAGAZ][Auth] Intento de login fallido.', [
                'email' => $validated['email'],
                'ip'    => $request->ip(),
            ]);
            throw ValidationException::withMessages([
                'email' => ['Credenciales incorrectas. Verifique su correo y contraseña.'],
            ]);
        }

        // Revocar tokens anteriores (sesión única)
        $usuario->tokens()->delete();

        $token = $usuario->createToken('dagaz-auth-token')->plainTextToken;

        Log::info('[DAGAZ][Auth] Login exitoso.', [
            'id_usuario' => $usuario->id_usuario,
            'email'      => $usuario->email,
            'rol'        => $usuario->rol,
        ]);

        return response()->json([
            'mensaje'    => 'Sesión iniciada exitosamente.',
            'usuario'    => [
                'id_usuario' => $usuario->id_usuario,
                'email'      => $usuario->email,
                'rol'        => $usuario->rol,
            ],
            'token'      => $token,
            'tipo_token' => 'Bearer',
        ]);
    }

    /**
     * Cerrar sesión del usuario autenticado.
     *
     * Revoca el token activo de Sanctum.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function logout(Request $request): JsonResponse
    {
        /** @var Usuario $usuario */
        $usuario = $request->user();

        if ($usuario) {
            $usuario->currentAccessToken()->delete();
            Log::info('[DAGAZ][Auth] Usuario cerró sesión.', [
                'id_usuario' => $usuario->id_usuario,
            ]);
        }

        return response()->json([
            'mensaje' => 'Sesión cerrada exitosamente.',
        ]);
    }

    /**
     * Obtener el perfil del usuario autenticado.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function perfil(Request $request): JsonResponse
    {
        /** @var Usuario $usuario */
        $usuario = $request->user();

        return response()->json([
            'usuario' => [
                'id_usuario'      => $usuario->id_usuario,
                'email'           => $usuario->email,
                'rol'             => $usuario->rol,
                'fecha_creacion'  => $usuario->fecha_creacion?->toIso8601String(),
                'es_administrador' => $usuario->esAdministrador(),
            ],
        ]);
    }

    /**
     * Exportar todos los datos personales del usuario (Ley 1581 de 2012 — Hábeas Data / TSK-4.5).
     *
     * Permite al titular consultar y descargar toda la información almacenada sobre él.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function datosPersonales(Request $request): JsonResponse
    {
        /** @var Usuario $usuario */
        $usuario = $request->user();

        $usuario->load(['contenidos', 'notas', 'historial']);

        Log::info('[DAGAZ][HabeasData] Exportación de datos personales solicitada.', [
            'id_usuario' => $usuario->id_usuario,
        ]);

        return response()->json([
            'aviso_legal' => 'Copia de datos personales bajo la Ley Estatutaria 1581 de 2012 (Colombia).',
            'fecha_exportacion' => now()->toIso8601String(),
            'titular' => [
                'id_usuario'     => $usuario->id_usuario,
                'email'          => $usuario->email,
                'rol'            => $usuario->rol,
                'fecha_creacion' => $usuario->fecha_creacion?->toIso8601String(),
            ],
            'actividad' => [
                'total_contenidos' => $usuario->contenidos->count(),
                'total_notas'      => $usuario->notas->count(),
                'total_historial'  => $usuario->historial->count(),
                'historial'        => $usuario->historial,
                'notas'            => $usuario->notas,
            ],
        ]);
    }

    /**
     * Ejercer derecho al olvido y supresión de datos (Ley 1581 de 2012 / TSK-4.5).
     *
     * Elimina la cuenta del usuario, revoca sus credenciales y tokens,
     * y aplica borrado en cascada sobre sus notas e historial.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function eliminarCuenta(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'password' => ['required', 'string'],
        ]);

        /** @var Usuario $usuario */
        $usuario = $request->user();

        if (! Hash::check($validated['password'], $usuario->password_hash)) {
            return response()->json([
                'mensaje' => 'Contraseña incorrecta. Se requiere confirmación para proceder con la supresión de la cuenta.',
            ], 422);
        }

        $idUsuario = $usuario->id_usuario;
        $email     = $usuario->email;

        // Revocar todos los tokens Sanctum
        $usuario->tokens()->delete();

        // Eliminar el registro (las FKs en cascada eliminan notas e historial; contenidos se setean a NULL)
        $usuario->delete();

        Log::info('[DAGAZ][HabeasData] Cuenta y datos personales suprimidos.', [
            'id_usuario' => $idUsuario,
            'email'      => $email,
        ]);

        return response()->json([
            'mensaje' => 'Su cuenta y datos personales han sido suprimidos exitosamente conforme a la Ley 1581 de 2012.',
        ]);
    }
}
