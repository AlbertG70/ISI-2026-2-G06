/**
 * DAGAZ Mobile — Sistema de diseño y colores
 * Basado en WCAG 2.1 AA (contraste >= 4.5:1), adaptado para adultos +40.
 */

export const COLORS = {
  papel: '#FAF8F5',       // Fondo cálido tipo periódico/editorial
  blanco: '#FFFFFF',
  tinta: '#1A1A1A',       // Texto primario alto contraste
  tintaSuave: '#555555',  // Texto secundario
  tintaMuted: '#777777',  // Metadatos
  borde: '#D1D5DB',       // Bordes de tarjetas y campos
  bordeFuerte: '#1A1A1A', // Bordes enfáticos
  marca: '#1F2937',       // Botones principales (Slate 800)
  marcaHover: '#111827',
  neutroBg: '#F3F4F6',    // Fondos secundarios

  // Paleta de veredictos semafóricos
  verdadero: {
    clave: 'VERDADERO',
    etiqueta: 'Verdadero',
    icono: '✅',
    color: '#166534',
    bg: '#F0FDF4',
    borde: '#BBF7D0',
    trazo: '#166534',
    resumen: 'La afirmación está respaldada por fuentes confiables.',
  },
  parcial: {
    clave: 'PARCIAL',
    etiqueta: 'Parcialmente cierto',
    icono: '⚠️',
    color: '#854D0E',
    bg: '#FEFCE8',
    borde: '#FEF08A',
    trazo: '#A16207',
    resumen: 'Tiene partes correctas, pero le falta contexto importante.',
  },
  enganoso: {
    clave: 'ENGANOSO',
    etiqueta: 'Engañoso',
    icono: '🟠',
    color: '#9A3412',
    bg: '#FFF7ED',
    borde: '#FED7AA',
    trazo: '#EA580C',
    resumen: 'Mezcla datos reales con información sacada de contexto.',
  },
  falso: {
    clave: 'FALSO',
    etiqueta: 'Falso',
    icono: '❌',
    color: '#991B1B',
    bg: '#FEF2F2',
    borde: '#FECACA',
    trazo: '#991B1B',
    resumen: 'Las fuentes confiables contradicen esta afirmación.',
  },
  noVerificable: {
    clave: 'NO_VERIFICABLE',
    etiqueta: 'No verificable',
    icono: '❔',
    color: '#374151',
    bg: '#F3F4F6',
    borde: '#E5E7EB',
    trazo: '#475569',
    resumen: 'No encontramos suficiente evidencia para confirmarlo o desmentirlo.',
  },
};

export function normalizarVeredicto(tipo) {
  if (!tipo) return COLORS.noVerificable;
  const normalizado = String(tipo)
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .toUpperCase()
    .replace(/\s+/g, '_');

  if (normalizado.includes('VERDAD')) return COLORS.verdadero;
  if (normalizado.includes('PARCIAL')) return COLORS.parcial;
  if (normalizado.includes('ENGAN')) return COLORS.enganoso;
  if (normalizado.includes('FALS')) return COLORS.falso;
  return COLORS.noVerificable;
}
