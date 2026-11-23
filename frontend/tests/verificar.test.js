import { describe, it, expect } from 'vitest';

describe('Validación de Entrada Multicanal (CU06)', () => {
    it('exige longitud mínima de 10 caracteres en la entrada de texto', () => {
        const textoCorto = 'Falso';
        expect(textoCorto.trim().length >= 10).toBe(false);

        const textoValido = 'Cierre de acueducto decretado para mañana en Bogotá';
        expect(textoValido.trim().length >= 10).toBe(true);
    });

    it('valida protocolos permitidos en enlaces URL (http / https)', () => {
        const esUrlValida = (str) => {
            try {
                const u = new URL(str);
                return ['http:', 'https:'].includes(u.protocol);
            } catch {
                return false;
            }
        };

        expect(esUrlValida('https://elespectador.com/noticia-bogota')).toBe(true);
        expect(esUrlValida('http://noticias.co/alerta')).toBe(true);
        expect(esUrlValida('javascript:alert(1)')).toBe(false);
        expect(esUrlValida('ftp://servidor.com')).toBe(false);
        expect(esUrlValida('no_es_una_url')).toBe(false);
    });

    it('controla el tamaño máximo de archivo a 20 MB', () => {
        const MAX_ARCHIVO = 20 * 1024 * 1024; // 20 MB

        const archivoPequeno = { size: 5 * 1024 * 1024 };
        expect(archivoPequeno.size <= MAX_ARCHIVO).toBe(true);

        const archivoGigante = { size: 25 * 1024 * 1024 };
        expect(archivoGigante.size <= MAX_ARCHIVO).toBe(false);
    });
});
