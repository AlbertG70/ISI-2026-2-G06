import AsyncStorage from '@react-native-async-storage/async-storage';

function esHostLocalOPrivado(hostSinEsquema) {
  const limpio = String(hostSinEsquema || '').split('/')[0].trim().toLowerCase();
  return (
    limpio.startsWith('localhost') ||
    limpio.startsWith('127.') ||
    limpio.startsWith('192.168.') ||
    limpio.startsWith('10.') ||
    /^172\.(1[6-9]|2\d|3[0-1])\./.test(limpio) ||
    limpio.startsWith('[::1]') ||
    /\.local(?::\d+)?$/.test(limpio) ||
    /:\d+$/.test(limpio)
  );
}

export function normalizarUrlServidor(url) {
  let limpia = String(url || '')
    .trim()
    .replace(/\/+$/, '')
    .replace(/\/api\/v1$/i, '');

  if (limpia && !/^https?:\/\//i.test(limpia)) {
    const esquema = esHostLocalOPrivado(limpia) ? 'http://' : 'https://';
    limpia = `${esquema}${limpia}`;
  }
  return limpia;
}

export const DEFAULT_SERVER_URL =
  normalizarUrlServidor(typeof process !== 'undefined' ? process.env?.EXPO_PUBLIC_API_URL : '') ||
  'http://192.168.1.32:8000'; // Editable en Mi cuenta > Conexión del Backend

let serverUrl = DEFAULT_SERVER_URL;

export async function initServerUrl() {
  try {
    const guardada = await AsyncStorage.getItem('dagaz_server_url');
    const limpia = normalizarUrlServidor(guardada);
    if (limpia) serverUrl = limpia;
  } catch {}
  return serverUrl;
}

export async function setServerUrl(url) {
  const limpia = normalizarUrlServidor(url) || DEFAULT_SERVER_URL;
  serverUrl = limpia;
  await AsyncStorage.setItem('dagaz_server_url', limpia);
  return limpia;
}

export function getServerUrl() {
  return serverUrl;
}

export async function probarConexionApi(urlPrueba) {
  const base = normalizarUrlServidor(urlPrueba) || serverUrl;
  const controller = new AbortController();
  const timeoutId = setTimeout(() => controller.abort(), 8000);
  try {
    const res = await fetch(`${base}/api/health`, {
      method: 'GET',
      headers: { Accept: 'application/json' },
      signal: controller.signal,
    });
    clearTimeout(timeoutId);
    const data = await res.json().catch(() => ({}));
    if (!res.ok) {
      throw new Error(data.mensaje || `El servidor respondió con estado ${res.status}.`);
    }
    return data;
  } catch (error) {
    clearTimeout(timeoutId);
    const msgLower = String(error?.message || '').toLowerCase();
    if (error?.name === 'AbortError' || msgLower.includes('cancel') || msgLower.includes('abort')) {
      throw new Error(`No hubo respuesta de ${base} en 8 segundos.`);
    }
    throw error;
  }
}

export async function obtenerToken() {
  try {
    return await AsyncStorage.getItem('dagaz_token');
  } catch {
    return null;
  }
}

export async function guardarToken(token) {
  if (token) {
    await AsyncStorage.setItem('dagaz_token', token);
  } else {
    await AsyncStorage.removeItem('dagaz_token');
  }
}

/**
 * Cliente HTTP general para la API REST v1 de DAGAZ
 */
export async function apiRequest(ruta, { method = 'GET', body, auth = true, timeoutMs = 30000 } = {}) {
  const token = auth ? await obtenerToken() : null;
  const headers = { Accept: 'application/json' };

  if (token) {
    headers.Authorization = `Bearer ${token}`;
  }

  let fetchBody = undefined;
  if (body instanceof FormData) {
    fetchBody = body;
  } else if (body !== undefined) {
    headers['Content-Type'] = 'application/json';
    fetchBody = JSON.stringify(body);
  }

  const endpoint = `${serverUrl}/api/v1${ruta.startsWith('/') ? ruta : '/' + ruta}`;

  const controller = new AbortController();
  const timeoutId = setTimeout(() => controller.abort(), timeoutMs);

  try {
    const res = await fetch(endpoint, {
      method,
      headers,
      body: fetchBody,
      signal: controller.signal,
    });
    clearTimeout(timeoutId);

    const data = await res.json().catch(() => ({}));
    if (res.ok) {
      return data;
    }

    const msg =
      data.mensaje ||
      data.message ||
      (data.errors && Object.values(data.errors).flat()[0]) ||
      `Error ${res.status}: no se pudo procesar la solicitud.`;
    const err = new Error(msg);
    err.status = res.status;
    err.errors = data.errors || {};
    throw err;
  } catch (error) {
    clearTimeout(timeoutId);
    const msgLower = String(error?.message || '').toLowerCase();
    if (error?.name === 'AbortError' || msgLower.includes('cancel') || msgLower.includes('abort')) {
      throw new Error(
        `Tiempo de espera agotado (${Math.round(timeoutMs / 1000)}s). El análisis del servidor tardó más de lo esperado o no responde en ${serverUrl}.`
      );
    }
    throw error;
  }
}

// ─────────────────────────────────────────────────────────────────────────────
// SERVICIOS ESPECÍFICOS DE CASOS DE USO
// ─────────────────────────────────────────────────────────────────────────────

// CU06: Verificar contenido (timeout ampliado a 120s para modelos de IA y búsqueda semántica)
export async function verificarContenido(datos) {
  return apiRequest('/verificacion', {
    method: 'POST',
    body: datos,
    auth: true,
    timeoutMs: 120000,
  });
}

// CU06 / CU04: Obtener detalle de verificación
export async function obtenerVerificacion(idContenido) {
  return apiRequest(`/verificacion/${encodeURIComponent(idContenido)}`, {
    method: 'GET',
    auth: false,
  });
}

// CU07: Catálogo de fuentes del grafo de credibilidad
export async function obtenerFuentes() {
  return apiRequest('/fuentes', {
    method: 'GET',
    auth: false,
  });
}

// CU07: Valorar confiabilidad de una fuente (0 a 100 vía PATCH)
export async function valorarFuenteApi(idFuente, calificacion) {
  return apiRequest(`/fuentes/${encodeURIComponent(idFuente)}/valorar`, {
    method: 'PATCH',
    body: { calificacion },
    auth: true,
  });
}

// CU04: Consultar historial del usuario
export async function obtenerHistorial(page = 1) {
  return apiRequest(`/historial?page=${encodeURIComponent(page)}`, {
    method: 'GET',
    auth: true,
  });
}

// CU03: Guardar en historial
export async function guardarEnHistorial(idContenido) {
  return apiRequest(`/historial/${encodeURIComponent(idContenido)}`, {
    method: 'POST',
    auth: true,
  });
}

// CU01: Listar notas comunitarias de un contenido
export async function listarNotas(idContenido) {
  return apiRequest(`/contenidos/${encodeURIComponent(idContenido)}/notas`, {
    method: 'GET',
    auth: true,
  });
}

// CU01: Agregar nota comunitaria
export async function agregarNotaComunitaria(idContenido, contenidoNota) {
  return apiRequest(`/contenidos/${encodeURIComponent(idContenido)}/notas`, {
    method: 'POST',
    body: { contenido_nota: contenidoNota },
    auth: true,
  });
}

// CU02: Calificar nota comunitaria (0.0 a 10.0 vía PATCH)
export async function calificarNota(idNota, calificacion) {
  return apiRequest(`/notas/${encodeURIComponent(idNota)}/calificar`, {
    method: 'PATCH',
    body: { calificacion },
    auth: true,
  });
}

// CU05: Login
export async function loginApi(email, password) {
  const data = await apiRequest('/auth/login', {
    method: 'POST',
    body: { email, password },
    auth: false,
  });
  if (data?.token) {
    await guardarToken(data.token);
  }
  return data;
}

// CU05: Registro
export async function registerApi(email, password, passwordConfirmation) {
  const data = await apiRequest('/auth/register', {
    method: 'POST',
    body: {
      email,
      password,
      password_confirmation: passwordConfirmation,
    },
    auth: false,
  });
  if (data?.token) {
    await guardarToken(data.token);
  }
  return data;
}

// CU05: Obtener perfil
export async function obtenerPerfilApi() {
  return apiRequest('/auth/perfil', {
    method: 'GET',
    auth: true,
  });
}

// Ley 1581: Descarga de datos personales
export async function exportarDatosPersonalesApi() {
  return apiRequest('/auth/datos-personales', {
    method: 'GET',
    auth: true,
  });
}

// Ley 1581: Derecho al olvido
export async function eliminarCuentaApi(password) {
  const data = await apiRequest('/auth/cuenta', {
    method: 'DELETE',
    body: { password },
    auth: true,
  });
  await guardarToken(null);
  return data;
}

// CU05: Logout
export async function logoutApi() {
  try {
    await apiRequest('/auth/logout', { method: 'POST', auth: true });
  } catch {}
  await guardarToken(null);
}
