import { beforeEach, describe, expect, it, vi } from 'vitest';
import { api } from '../src/js/dagaz/api';
import { iniciar } from '../src/js/dagaz/paginas/resultado';
import vista from '../views/verificacion/resultado.blade.php?raw';

vi.mock('../src/js/dagaz/api', () => ({ api: vi.fn() }));

const explicacion = 'Análisis completo del aviso.\n\nAquí se describe cómo se contrastó el horario.';

function responderCon(veredicto) {
    api.mockResolvedValue({
        contenido: { contenido_original: 'La biblioteca abre el sábado de 8 a 12.' },
        veredicto: { tipo_veredicto: 'PARCIAL', nivel_confiabilidad: 70, explicacion, ...veredicto },
    });
}

describe('Explicación del resultado', () => {
    beforeEach(() => {
        document.body.dataset.idContenido = 'verificacion-de-prueba';
        document.body.innerHTML = vista;
    });

    it('abre el motivo breve y enlaza a las citas de la sección de fuentes', async () => {
        const motivo = 'La biblioteca abre el sábado. Su aviso indica que abre a las 10.';
        responderCon({
            motivo_breve: motivo,
            fuentes: [{ nombre: 'Biblioteca', cita_contraste: 'Horario del sábado de 10 a 12.' }],
        });

        await iniciar();

        const motivoAbierto = document.querySelector('#bloque-veredicto details[open]');
        expect(motivoAbierto.querySelector('p').textContent).toBe(motivo);
        const enlace = motivoAbierto.querySelector('a');
        expect(enlace.textContent).toBe('Ver fuentes consultadas');
        expect(document.querySelector(enlace.getAttribute('href'))).not.toBeNull();
        expect(document.querySelector('#lista-fuentes').textContent).toContain('Horario del sábado de 10 a 12.');
        expect(motivoAbierto.querySelector('details').open).toBe(false);
    });

    it('conserva la explicación de un veredicto anterior en un único desplegable', async () => {
        responderCon({ fuentes: [{ cita_contraste: '  ' }] });

        await iniciar();

        const bloque = document.querySelector('#bloque-veredicto');
        const detalles = bloque.querySelectorAll('details');
        expect(detalles).toHaveLength(1);
        expect(detalles[0].open).toBe(false);
        expect(detalles[0].querySelector('summary').textContent).toBe('Ver explicación completa');
        expect(detalles[0].querySelector('p').textContent).toBe(explicacion);
        expect(bloque.querySelector('a[href="#titulo-fuentes"]')).toBeNull();
    });
});
