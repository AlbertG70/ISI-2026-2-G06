import { describe, it, expect, beforeEach, vi } from 'vitest';
import { api } from '../src/js/dagaz/api';
import { guardarSesion, cerrarSesionLocal } from '../src/js/dagaz/sesion';

describe('Cliente API REST (DAGAZ Frontend)', () => {
    beforeEach(() => {
        cerrarSesionLocal();
        vi.restoreAllMocks();
    });

    it('incluye encabezados Accept y Content-Type en peticiones con cuerpo', async () => {
        global.fetch = vi.fn().mockResolvedValue({
            ok: true,
            status: 200,
            json: async () => ({ ok: true }),
        });

        await api('/test', { method: 'POST', body: { dato: '123' } });

        expect(global.fetch).toHaveBeenCalledTimes(1);
        const [url, opciones] = global.fetch.mock.calls[0];
        expect(url).toBe('/api/v1/test');
        expect(opciones.headers['Accept']).toBe('application/json');
        expect(opciones.headers['Content-Type']).toBe('application/json');
    });

    it('adjunta el token Bearer cuando hay sesión activa', async () => {
        guardarSesion({ token: 'sanctum_token_123', usuario: { email: 'test@dagaz.co' } });

        global.fetch = vi.fn().mockResolvedValue({
            ok: true,
            status: 200,
            json: async () => ({ autenticado: true }),
        });

        await api('/auth/perfil');

        const [, opciones] = global.fetch.mock.calls[0];
        expect(opciones.headers['Authorization']).toBe('Bearer sanctum_token_123');
    });

    it('lanza un error estructurado cuando la API retorna 422 con errores de validación', async () => {
        global.fetch = vi.fn().mockResolvedValue({
            ok: false,
            status: 422,
            json: async () => ({
                mensaje: 'Datos no válidos.',
                errors: { email: ['El correo ya existe.'] },
            }),
        });

        await expect(api('/auth/register', { method: 'POST' })).rejects.toMatchObject({
            status: 422,
            message: 'Datos no válidos.',
            errores: { email: ['El correo ya existe.'] },
        });
    });
});
