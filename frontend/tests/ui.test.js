import { describe, it, expect } from 'vitest';
import { el, metaVeredicto, insigniaVeredicto, medidorCircular, aliasAutor } from '../src/js/dagaz/ui';

describe('Utilidades de Interfaz y Seguridad DOM (ui.js)', () => {
    it('previene inyecciones XSS forzando el uso de textContent en el builder el()', () => {
        const payloadMalicioso = '<script>alert("hack")</script><img src=x onerror=alert(1)>';
        const elemento = el('div', { text: payloadMalicioso });

        // El contenido debe ser texto plano escapado, no nodos hijos HTML
        expect(elemento.innerHTML).not.toContain('<script>');
        expect(elemento.innerHTML).toContain('&lt;script&gt;');
        expect(elemento.textContent).toBe(payloadMalicioso);
        expect(elemento.children.length).toBe(0);
    });

    it('asigna metadatos cromáticos y semánticos correctos para cada veredicto', () => {
        const verdadero = metaVeredicto('VERDADERO');
        expect(verdadero.etiqueta).toBe('Verdadero');
        expect(verdadero.icono).toBe('✅');

        const falso = metaVeredicto('FALSO');
        expect(falso.etiqueta).toBe('Falso');
        expect(falso.icono).toBe('❌');

        const parcial = metaVeredicto('PARCIAL');
        expect(parcial.etiqueta).toBe('Parcialmente cierto');
        expect(parcial.icono).toBe('⚠️');
    });

    it('renderiza la insignia de veredicto accesible con rol de estado', () => {
        const insignia = insigniaVeredicto('VERDADERO');
        expect(insignia.getAttribute('role')).toBe('status');
        expect(insignia.textContent).toContain('Verdadero');
    });

    it('genera el medidor circular SVG con porcentaje legible', () => {
        const medidor = medidorCircular(85, 60);
        expect(medidor.querySelector('svg')).not.toBeNull();
        expect(medidor.textContent).toBe('85%');
    });

    it('anonimiza o genera alias amigables para autores comunitarios', () => {
        expect(aliasAutor('carlos.perez@correo.com')).toBe('ca••••••');
        expect(aliasAutor(null)).toBe('Usuario de la comunidad');
    });
});
