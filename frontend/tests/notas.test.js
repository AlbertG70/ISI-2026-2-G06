import { describe, it, expect } from 'vitest';

describe('Notas Comunitarias y Calificación (CU01 / CU02)', () => {
    const CARACTERES_INVALIDOS = /[<>{}\\\|]/;

    it('detecta caracteres potencialmente peligrosos según el mockup CU01', () => {
        expect(CARACTERES_INVALIDOS.test('Texto con <tag>')).toBe(true);
        expect(CARACTERES_INVALIDOS.test('Texto con {json}')).toBe(true);
        expect(CARACTERES_INVALIDOS.test('Texto con \\ barra')).toBe(true);
        expect(CARACTERES_INVALIDOS.test('Texto con | pipe')).toBe(true);
        expect(CARACTERES_INVALIDOS.test('Texto normal y legítimo de un adulto de 50 años.')).toBe(false);
    });

    it('calcula porcentajes de utilidad acotados entre 0 y 100%', () => {
        const cal1 = 7.5;
        const pct1 = Math.min(100, Math.max(0, Math.round((cal1 / 10) * 100)));
        expect(pct1).toBe(75);

        const cal2 = 10.0;
        const pct2 = Math.min(100, Math.max(0, Math.round((cal2 / 10) * 100)));
        expect(pct2).toBe(100);

        const cal3 = 0.0;
        const pct3 = Math.min(100, Math.max(0, Math.round((cal3 / 10) * 100)));
        expect(pct3).toBe(0);
    });

    it('clasifica notas según filtro de utilidad (útiles >= 6.0)', () => {
        const notas = [
            { id: 1, calificacion: 8.5 },
            { id: 2, calificacion: 4.0 },
            { id: 3, calificacion: 6.0 },
        ];

        const utiles = notas.filter((n) => Number(n.calificacion) >= 6);
        const noUtiles = notas.filter((n) => Number(n.calificacion) < 6);

        expect(utiles.length).toBe(2);
        expect(noUtiles.length).toBe(1);
        expect(noUtiles[0].id).toBe(2);
    });
});
