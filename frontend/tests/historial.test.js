import { describe, it, expect, beforeEach } from 'vitest';
import { claveVeredicto } from '../src/js/dagaz/ui';

describe('Gestión y Filtrado de Historial (CU04)', () => {
    let items;

    beforeEach(() => {
        items = [
            { id_contenido: 'c1', resumen_contenido: 'Crisis de agua potable en la ciudad', veredicto: { tipo_veredicto: 'VERDADERO', nivel_confiabilidad: 92 }, guardado: true },
            { id_contenido: 'c2', resumen_contenido: 'Vacuna causa efectos no reportados', veredicto: { tipo_veredicto: 'FALSO', nivel_confiabilidad: 88 }, guardado: false },
            { id_contenido: 'c3', resumen_contenido: 'Descubren nuevo mineral en la luna', veredicto: { tipo_veredicto: 'NO_VERIFICABLE', nivel_confiabilidad: 30 }, guardado: true },
        ];
    });

    it('filtra correctamente solo las noticias guardadas', () => {
        const guardados = items.filter((i) => i.guardado);
        expect(guardados.length).toBe(2);
        expect(guardados.map((i) => i.id_contenido)).toEqual(['c1', 'c3']);
    });

    it('filtra por tipo de veredicto normalizado', () => {
        const falsos = items.filter((i) => claveVeredicto(i.veredicto?.tipo_veredicto) === 'FALSO');
        expect(falsos.length).toBe(1);
        expect(falsos[0].id_contenido).toBe('c2');
    });

    it('busca por palabras clave insensibles a mayúsculas', () => {
        const busqueda = 'agua';
        const resultados = items.filter((i) => i.resumen_contenido.toLowerCase().includes(busqueda));
        expect(resultados.length).toBe(1);
        expect(resultados[0].id_contenido).toBe('c1');
    });
});
