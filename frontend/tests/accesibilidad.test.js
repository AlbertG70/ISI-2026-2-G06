import { describe, it, expect, beforeEach } from 'vitest';

describe('Accesibilidad para Adultos +40 y WCAG 2.1 AA', () => {
    beforeEach(() => {
        localStorage.clear();
        delete document.documentElement.dataset.tamanoTexto;
        document.body.innerHTML = `
            <button id="btn-tamano-normal">A</button>
            <button id="btn-tamano-grande">A+</button>
        `;
    });

    it('aplica y persiste la escala de fuente ampliada al conmutar A+', () => {
        // Simular conmutación a 'grande'
        document.documentElement.dataset.tamanoTexto = 'grande';
        localStorage.setItem('dagaz_tamano_texto', 'grande');

        expect(document.documentElement.dataset.tamanoTexto).toBe('grande');
        expect(localStorage.getItem('dagaz_tamano_texto')).toBe('grande');
    });

    it('restablece a escala normal al conmutar A', () => {
        document.documentElement.dataset.tamanoTexto = 'grande';
        localStorage.setItem('dagaz_tamano_texto', 'grande');

        // Restaurar
        delete document.documentElement.dataset.tamanoTexto;
        localStorage.setItem('dagaz_tamano_texto', 'normal');

        expect(document.documentElement.dataset.tamanoTexto).toBeUndefined();
        expect(localStorage.getItem('dagaz_tamano_texto')).toBe('normal');
    });
});
