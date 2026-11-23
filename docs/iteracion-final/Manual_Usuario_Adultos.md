# DAGAZ — Manual de Usuario para Adultos (+40 Años)
## Guía Ciudadana de Verificación de Noticias y Cadenas de Redes Sociales

> **Proyecto:** DAGAZ — Plataforma de Verificación de Información y Desinformación en Colombia  
> **Versión:** `v1.4.1` (Web Responsiva + App Móvil Android/iOS)  
> **Asignatura:** Ingeniería de Servicios de Internet — 2026-2 — Grupo 06  
> **Requisito Asociado:** REQ-27 (Manuales de Usuario y Despliegue)  
> **Diseñado para:** Adultos y adultos mayores (+40 años) que reciben información en WhatsApp, Facebook, X (Twitter) o medios digitales.

---

## 1. Bienvenido a DAGAZ 🛡️

**DAGAZ** es una plataforma ciudadana gratuita diseñada para ayudarle a comprobar si una noticia, un mensaje reenviado de WhatsApp, un titular o una captura de pantalla es **verdadera, parcialmente cierta, engañosa o falsa**.

En Colombia circulan a diario miles de mensajes con información no confirmada que pueden generar alarma o confusión. DAGAZ analiza el contenido comparándolo con **fuentes oficiales, medios reconocidos y verificadores certificados** (como ColombiaCheck, AFP Factual y La Silla Vacía) para entregarle una explicación sencilla y clara, tanto en el **portal web** (`/`) como desde la **aplicación móvil DAGAZ** (Android e iOS).

---

## 2. Accesibilidad: Cómo Ajustar el Tamaño de la Letra (Modo Lectura Cómoda) 🔍

Sabemos que leer textos pequeños en el teléfono o computador puede cansar la vista. Por eso, DAGAZ incluye una función especial de tamaño de letra:

1. En la barra superior de navegación encontrará el botón **`A / A+`** (en la app móvil también puede activarlo desde **Perfil / Accesibilidad**).
2. Al pulsar **`A / A+`**, el tamaño de todo el texto aumentará automáticamente (letra grande de fácil lectura desde 18px base hacia escala ampliada).
3. Su preferencia quedará **guardada en su dispositivo** (`localStorage` en Web o almacenamiento local seguro en móvil), por lo que no tendrá que configurarla cada vez que ingrese.

> 💡 **Consejo:** Todos los botones interactivos de DAGAZ son grandes (mínimo **48px** de altura/área táctil) para que pueda pulsarlos con facilidad desde su teléfono móvil sin equivocarse.

---

## 3. ¿Cómo Verificar un Mensaje o Noticia? (Paso a Paso) ✍️

### Paso 1: Ingresar a la sección de Verificación
* En el menú principal, pulse **"🔍 Verificar"** (ruta web `/` o `/verificar`, o pestaña **Verificar** en la aplicación móvil).

### Paso 2: Seleccionar el tipo de contenido y pegarlo
* **Texto (`TEXTO`):** Copie el texto de la cadena de WhatsApp o titular y péguelo en la caja principal (mínimo **10 caracteres**, por ejemplo: *"El gobierno anunció subsidio extraordinario de vivienda para el próximo mes"*).
* **Enlace Web (`URL`) o Red Social (`RED_SOCIAL`):** Pegue el enlace completo `http://` o `https://` de la noticia o publicación.
* **Captura de Pantalla o Imagen (`IMAGEN_OCR`):** Adjunte una foto o captura de pantalla (hasta **20 MB**) para que el servicio OCR extraiga automáticamente el texto visible.

### Paso 3: Pulsar "Verificar Ahora"
* Pulse el botón principal **"Comprobar / Verificar Afirmación"**.
* El sistema contrastará la afirmación frente a la base documental semántica de **80 fuentes de credibilidad** y el **Consejo Multi-Modelo de IA**, mostrando el resultado en `/verificar/{id}` (o en la pantalla **Resultado** del móvil).

---

## 4. ¿Cómo Entender el Veredicto y el Puntaje de Confianza? 📊

Una vez finalizado el análisis, verá una pantalla con cuatro bloques claros:

### A. La Etiqueta de Veredicto
DAGAZ utiliza cinco categorías oficiales de dictamen:
* 🟢 **Verdadero (`VERDADERO`):** La afirmación está respaldada de forma consistente por fuentes primarias e institucionales confiables.
* 🟨 **Parcialmente cierto (`PARCIAL`):** Contiene elementos verídicos, pero omite contexto clave o mezcla hechos reales con precisiones incompletas.
* 🟠 **Engañoso (`ENGAÑOSO`):** Manipula el sentido de los hechos, exagera o presenta noticias antiguas fuera de contexto (detectado también mediante **Archive.org / Wayback Machine**).
* 🔴 **Falso (`FALSO`):** La afirmación fue desmentida o carece de sustento fáctico frente a la evidencia contrastada.
* ⚪ **No Verificable (`NO_VERIFICABLE`):** No existen registros públicos concluyentes que permitan confirmar o desmentir el hecho.

### B. El Medidor de Confianza (Puntaje de 0% a 100%)
* **75% – 100% (Verde):** Alta solidez de respaldo fáctico.
* **55% – 74% (Amarillo/Ámbar):** Evidencia parcial o con matices que conviene leer con detalle.
* **Menor a 55% (Naranja/Rojo):** Alto riesgo de tergiversación, descontextualización o falsedad.

### C. La Explicación en Lenguaje Sencillo y Consenso de IA
Debajo del puntaje encontrará un resumen claro que explica **por qué** se llegó a ese resultado, junto con los enlaces a las **fuentes consultadas** y, cuando está habilitado, el resumen del consenso del **Consejo Multi-Modelo** y las verificaciones interlingües (`ES/EN`).

---

## 5. Notas Comunitarias: Aporte de la Comunidad y Calificaciones 🤝

DAGAZ cuenta con un sistema de **Notas Comunitarias** (CU01 / CU02):

1. **Lectura de notas:** Al final de cada veredicto verá aportes explicativos de otros ciudadanos verificadores (y podrá filtrarlos en `Todas`, `👍 Útiles` o `👎 No útiles`).
2. **Calificar una nota:** Si la nota le pareció clara y útil, pulse **"👍 Útil"** o **"👎 No útil"**.
3. **Escribir una nota:** Si usted tiene una sesión iniciada y cuenta con fuentes verificadas adicionales, puede redactar una nota (mínimo **20 caracteres** en interfaz web, máximo **2.000 caracteres** en formulario; validado de 10 a 2.000 en API).
   * *Nota de seguridad:* Para proteger a la comunidad, el sistema bloquea automáticamente etiquetas peligrosas o código (`<script>`, `<`, `>`, `{`, `}`, entre otros).

---

## 6. Historial de Consultas, Guardado y Directorio de Fuentes 🔖

* **Historial (`/historial`):** Consulte sus verificaciones recientes, pulse **⭐ Guardar noticia** (`POST /api/v1/historial/{idContenido}`) para marcarla en favoritos y filtre por palabras clave o tipo de veredicto.
* **Fuentes Confiables (`/fuentes`):** Explore las **80 fuentes** clasificadas en 4 niveles de credibilidad y emita su valoración ciudadana si ha iniciado sesión.

---

## 7. Privacidad y Protección de Datos Personales (Ley 1581 de 2012) 🔒

En cumplimiento del marco legal colombiano de **Hábeas Data (Ley Estatutaria 1581 de 2012)**:

1. **Anonimato en la comunidad:** Cuando usted publica una nota comunitaria, su correo electrónico se muestra protegido y enmascarado (por ejemplo, `ca••••••@gmail.com`) para resguardar su identidad.
2. **Descarga de sus datos:** En su **Perfil (`/perfil`)**, pulse **"📥 Descargar mis datos (JSON)"** (`GET /api/v1/auth/datos-personales`) para obtener una copia completa de su cuenta, historial y notas.
3. **Derecho al olvido y cancelación:** En cualquier momento puede solicitar la eliminación definitiva de su cuenta y sus datos desde el mismo panel de perfil (`DELETE /api/v1/auth/cuenta`).

---

## 8. Preguntas Frecuentes (FAQ) ❓

* **¿Tiene algún costo usar DAGAZ?**  
  No, el uso ciudadano para verificar afirmaciones es 100% gratuito.
* **¿Necesito crear cuenta para verificar una noticia?**  
  No. Cualquier ciudadano puede verificar texto, enlaces o capturas de forma abierta. La cuenta gratuita solo se necesita para guardar historial personal, publicar/calificar notas comunitarias o valorar fuentes.
* **¿Qué hago si no estoy de acuerdo con un veredicto?**  
  Puede publicar una Nota Comunitaria aportando enlaces de fuentes oficiales o solicitar revisión; los editores y administradores pueden auditar e intervenir el veredicto con trazabilidad pública.
* **¿Puedo compartir el resultado en WhatsApp?**  
  Sí, en la pantalla de resultado pulse el botón **"📲 Compartir"** para enviar un resumen o copiar el enlace directo `/verificar/{id}` a sus familiares o grupos.
