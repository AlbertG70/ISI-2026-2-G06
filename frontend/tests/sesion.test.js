import { describe, it, expect, beforeEach } from 'vitest';
import { guardarSesion, obtenerToken, obtenerUsuario, cerrarSesionLocal } from '../src/js/dagaz/sesion';

describe('Gestor de Sesión y Autenticación (sesion.js)', () => {
    beforeEach(() => {
        cerrarSesionLocal();
    });

    it('guarda y recupera el token y el perfil de usuario correctamente', () => {
        guardarSesion({
            token: 'test_token_abc',
            usuario: { id_usuario: 'u1', email: 'editor@dagaz.co', rol: 'editor' },
        });

        expect(obtenerToken()).toBe('test_token_abc');
        const usuario = obtenerUsuario();
        expect(usuario.email).toBe('editor@dagaz.co');
        expect(usuario.rol).toBe('editor');
    });

    it('elimina los tokens y el usuario al cerrar sesión', () => {
        guardarSesion({ token: 'abc', usuario: { email: 'user@test.co' } });
        expect(obtenerToken()).toBe('abc');

        cerrarSesionLocal();
        expect(obtenerToken()).toBeNull();
        expect(obtenerUsuario()).toBeNull();
    });
});
