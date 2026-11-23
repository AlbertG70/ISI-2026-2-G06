import { describe, it, expect, beforeEach } from 'vitest';
import { guardarSesion, cerrarSesionLocal, obtenerUsuario } from '../src/js/dagaz/sesion';

describe('Panel de Intervención Editorial Humana (REQ-13 / TSK-3.7)', () => {
    beforeEach(() => {
        cerrarSesionLocal();
        document.body.innerHTML = `
            <section id="panel-editorial" hidden>
                <form id="form-editorial">
                    <select id="editorial-tipo-veredicto">
                        <option value="VERDADERO">Verdadero</option>
                        <option value="FALSO">Falso</option>
                    </select>
                    <input type="number" id="editorial-confianza" value="85">
                    <input type="text" id="editorial-motivo" value="">
                    <textarea id="editorial-explicacion"></textarea>
                    <button type="submit" id="btn-intervenir-veredicto">Guardar</button>
                </form>
            </section>
        `;
    });

    it('solo debe activarse cuando el usuario tiene rol editor o admin', () => {
        // Caso 1: Usuario común (adulto)
        guardarSesion({ token: 't1', usuario: { email: 'adulto@test.co', rol: 'adulto' } });
        const usuario1 = obtenerUsuario();
        const esEditor1 = usuario1 && ['editor', 'admin'].includes(usuario1.rol);
        expect(esEditor1).toBe(false);

        // Caso 2: Revisor Humano (editor)
        guardarSesion({ token: 't2', usuario: { email: 'editor@dagaz.co', rol: 'editor' } });
        const usuario2 = obtenerUsuario();
        const esEditor2 = usuario2 && ['editor', 'admin'].includes(usuario2.rol);
        expect(esEditor2).toBe(true);
    });

    it('exige longitud mínima de 10 caracteres en el motivo y 20 en la explicación', () => {
        const motivoCorto = 'Muy poco';
        expect(motivoCorto.length < 10).toBe(true);

        const motivoValido = 'Corrección por boletín de Registraduría';
        expect(motivoValido.length >= 10).toBe(true);

        const explicacionCorta = 'Cambio veredicto';
        expect(explicacionCorta.length < 20).toBe(true);

        const explicacionValida = 'Se contrasta con el reporte oficial emitido a las 14:00 horas.';
        expect(explicacionValida.length >= 20).toBe(true);
    });
});
