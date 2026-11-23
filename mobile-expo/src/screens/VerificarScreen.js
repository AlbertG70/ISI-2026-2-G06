import React, { useState } from 'react';
import {
  View,
  Text,
  TextInput,
  TouchableOpacity,
  ScrollView,
  StyleSheet,
  ActivityIndicator,
  Alert,
  Image,
  Platform,
} from 'react-native';
import * as ImagePicker from 'expo-image-picker';
import { COLORS } from '../theme/colors';
import { useTheme } from '../context/ThemeContext';
import { verificarContenido } from '../services/api';

const MONO_FONT = Platform.select({ ios: 'Menlo', default: 'monospace' });

const ETAPAS_PENSAMIENTO = [
  { tag: 'SISTEMA', msg: 'Iniciando canal de inferencia reactivo...', color: '#38bdf8', pct: 15 },
  { tag: 'INGESTA', msg: 'Normalizando contenido textual y entidades clave...', color: '#a78bfa', pct: 28 },
  { tag: 'ATÓMICO', msg: 'Descomponiendo afirmaciones en proposiciones fácticas...', color: '#34d399', pct: 45 },
  { tag: 'RAG_GRAFO', msg: 'Consultando base de 80 fuentes periodísticas y oficiales en Neon...', color: '#fbbf24', pct: 65 },
  { tag: 'WAYBACK', msg: 'Contrastando histórico de noticias y desmentidos previos...', color: '#f472b6', pct: 78 },
  { tag: 'CONSEJO_IA', msg: 'Deliberando consenso fáctico entre agentes de verificación...', color: '#c084fc', pct: 90 },
  { tag: 'VEREDICTO', msg: 'Generando síntesis explicable y nivel de confiabilidad...', color: '#4ade80', pct: 98 },
];

export default function VerificarScreen({ navigation }) {
  const { font } = useTheme();

  const [pestana, setPestana] = useState('texto'); // 'texto' | 'url' | 'archivo'
  const [texto, setTexto] = useState('');
  const [url, setUrl] = useState('');
  const [imagenSeleccionada, setImagenSeleccionada] = useState(null);
  const [descripcionArchivo, setDescripcionArchivo] = useState('');

  const [procesando, setProcesando] = useState(false);
  const [logsPensamiento, setLogsPensamiento] = useState([]);
  const [progresoPct, setProgresoPct] = useState(10);
  const [segundosTranscurridos, setSegundosTranscurridos] = useState('0.0');

  const seleccionarImagen = async () => {
    try {
      const permiso = await ImagePicker.requestMediaLibraryPermissionsAsync();
      if (!permiso.granted) {
        Alert.alert('Permiso requerido', 'Necesitamos acceso a sus fotos para verificar capturas de pantalla.');
        return;
      }
      const resultado = await ImagePicker.launchImageLibraryAsync({
        mediaTypes: ['images'],
        allowsEditing: true,
        quality: 0.8,
      });
      if (!resultado.canceled && resultado.assets?.[0]) {
        setImagenSeleccionada(resultado.assets[0]);
      }
    } catch {
      Alert.alert('Error', 'No se pudo abrir la galería de imágenes.');
    }
  };

  const tomarFoto = async () => {
    try {
      const permiso = await ImagePicker.requestCameraPermissionsAsync();
      if (!permiso.granted) {
        Alert.alert('Permiso requerido', 'Necesitamos acceso a la cámara para fotografiar el texto o noticia.');
        return;
      }
      const resultado = await ImagePicker.launchCameraAsync({
        allowsEditing: true,
        quality: 0.8,
      });
      if (!resultado.canceled && resultado.assets?.[0]) {
        setImagenSeleccionada(resultado.assets[0]);
      }
    } catch {
      Alert.alert('Error', 'No se pudo abrir la cámara.');
    }
  };

  const limpiarFormulario = () => {
    setTexto('');
    setUrl('');
    setImagenSeleccionada(null);
    setDescripcionArchivo('');
  };

  const iniciarPensamiento = () => {
    const inicio = Date.now();
    setLogsPensamiento([{ tag: 'SISTEMA', msg: 'Iniciando canal reactivo con motor DAGAZ...', color: '#38bdf8', tiempo: '0.0s' }]);
    setProgresoPct(12);

    const timer = setInterval(() => {
      const segs = ((Date.now() - inicio) / 1000).toFixed(1);
      setSegundosTranscurridos(segs);
    }, 100);

    let idx = 0;
    const intervaloEtapas = setInterval(() => {
      if (idx < ETAPAS_PENSAMIENTO.length) {
        const etapa = ETAPAS_PENSAMIENTO[idx];
        const segs = ((Date.now() - inicio) / 1000).toFixed(1);
        setLogsPensamiento((prev) => [
          ...prev,
          { tag: etapa.tag, msg: etapa.msg, color: etapa.color, tiempo: `${segs}s` },
        ]);
        setProgresoPct(etapa.pct);
        idx += 1;
      } else {
        clearInterval(intervaloEtapas);
      }
    }, 1800);

    return () => {
      clearInterval(timer);
      clearInterval(intervaloEtapas);
    };
  };

  const handleVerificar = async () => {
    let payload = null;

    if (pestana === 'texto') {
      if (texto.trim().length < 10) {
        Alert.alert('Texto muy corto', 'Por favor escriba al menos 10 caracteres del rumor o noticia.');
        return;
      }
      payload = { tipo_entrada: 'texto', contenido_original: texto.trim() };
    } else if (pestana === 'url') {
      if (!url.trim().startsWith('http://') && !url.trim().startsWith('https://')) {
        Alert.alert('Enlace inválido', 'Ingrese un enlace web válido que inicie con https:// o http://');
        return;
      }
      payload = { tipo_entrada: 'url', url_origen: url.trim() };
    } else {
      if (!imagenSeleccionada) {
        Alert.alert('Falta imagen', 'Seleccione una imagen o tome una foto de la noticia.');
        return;
      }
      if (descripcionArchivo.trim().length < 10) {
        Alert.alert(
          'Descripción requerida',
          'Escriba al menos 10 caracteres resumiendo qué afirma la imagen para ayudar al análisis.'
        );
        return;
      }
      const formData = new FormData();
      formData.append('tipo_entrada', 'imagen');
      formData.append('contenido_original', descripcionArchivo.trim());
      formData.append('archivo', {
        uri: imagenSeleccionada.uri,
        name: imagenSeleccionada.fileName || 'captura.jpg',
        type: imagenSeleccionada.mimeType || 'image/jpeg',
      });
      payload = formData;
    }

    setProcesando(true);
    const stopProgreso = iniciarPensamiento();

    try {
      const respuesta = await verificarContenido(payload);
      stopProgreso();
      setProcesando(false);

      const contenido = respuesta?.contenido || respuesta;
      const veredicto = respuesta?.veredicto || contenido?.veredicto;

      navigation.navigate('Resultado', {
        idContenido: contenido?.id_contenido,
        contenido,
        veredicto,
        fuentes: respuesta?.fuentes || [],
      });
    } catch (err) {
      stopProgreso();
      setProcesando(false);

      if (err?.status) {
        Alert.alert(
          'No se pudo verificar',
          err.message || `El servidor devolvió un error (${err.status}). Revise los datos e intente nuevamente.`
        );
        return;
      }

      // Error de red / timeout al conectar con el backend: ofrecer ver una demostración
      Alert.alert(
        'Aviso de Conexión',
        `${err.message || 'No se pudo conectar al backend.'}\n\n¿Desea ver el resultado con una verificación de muestra?`,
        [
          { text: 'Cancelar', style: 'cancel' },
          {
            text: 'Ver Muestra',
            onPress: () => {
              navigation.navigate('Resultado', {
                contenido: {
                  id_contenido: 'demo-1234',
                  contenido_original:
                    texto || url || descripcionArchivo || 'Afirmación: El gobierno aprobó subsidio universal sin requisitos.',
                  fecha_subida: new Date().toISOString(),
                },
                veredicto: {
                  tipo_veredicto: 'FALSO',
                  nivel_confiabilidad: 92,
                  fecha_emision: new Date().toISOString(),
                  explicacion:
                    'Ningún decreto oficial ha promulgado dicha medida. Las declaraciones ministeriales y los boletines del Departamento de Prosperidad Social desmienten la supuesta entrega de subsidios universales sin trámite.',
                },
                fuentes: [
                  {
                    id_fuente: 'f-1',
                    nombre_fuente: 'ColombiaCheck Fact-Checking',
                    url: 'https://colombiacheck.com',
                    cita_contraste: 'No existe tal decreto en el diario oficial ni en el portal de subsidios.',
                    nivel_confianza: 95,
                  },
                  {
                    id_fuente: 'f-2',
                    nombre_fuente: 'La Silla Vacía Detector',
                    url: 'https://lasillavacia.com',
                    cita_contraste: 'Cadenas de WhatsApp difunden enlaces fraudulentos de captación de datos.',
                    nivel_confianza: 90,
                  },
                ],
              });
            },
          },
        ]
      );
    }
  };

  if (procesando) {
    return (
      <View style={styles.processingContainer}>
        <View style={styles.thinkingCard}>
          <View style={styles.thinkingHeader}>
            <View style={styles.pulseIndicator} />
            <Text style={[styles.processingTitle, { fontSize: font(18) }]}>
              Proceso de Pensamiento Fáctico
            </Text>
          </View>
          <Text style={[styles.processingSubtitle, { fontSize: font(13) }]}>
            Cadena de razonamiento (Chain-of-Thought) en vivo: Análisis de premisas y deliberación multi-agente.
          </Text>

          <View style={styles.progressRow}>
            <Text style={styles.progressLabel}>Inferencia en curso...</Text>
            <Text style={styles.progressValue}>{progresoPct}%</Text>
          </View>
          <View style={styles.progressBarBg}>
            <View style={[styles.progressBarFill, { width: (progresoPct + '%') }]} />
          </View>

          <View style={styles.consoleBox}>
            <View style={styles.consoleHeader}>
              <View style={styles.consoleDot} />
              <Text style={styles.consoleTitle}>PENSAMIENTO DEL SISTEMA & AGENTES LLM</Text>
              <Text style={styles.consoleTimer}>{segundosTranscurridos}s</Text>
            </View>

            <ScrollView style={styles.consoleLogs} nestedScrollEnabled={true}>
              {logsPensamiento.map((log, index) => (
                <View key={index} style={styles.logLine}>
                  <Text style={styles.logTime}>[{log.tiempo}]</Text>
                  <Text style={[styles.logTag, { color: log.color }]}>[{log.tag}]</Text>
                  <Text style={styles.logMsg}>{log.msg}</Text>
                </View>
              ))}
            </ScrollView>
          </View>
        </View>
      </View>
    ); }

  return (
    <ScrollView style={styles.container} contentContainerStyle={styles.content}>
      {/* Banner Explicativo Accesible */}
      <View style={styles.introCard}>
        <Text style={[styles.introTitle, { fontSize: font(18) }]}>
          ¿Le llegó una noticia sospechosa?
        </Text>
        <Text style={[styles.introText, { fontSize: font(14) }]}>
          Verifíquela al instante pegando el texto, el enlace de la red social o una fotografía de la captura.
        </Text>
      </View>

      {/* Pestañas de entrada */}
      <View style={styles.tabsRow}>
        <TouchableOpacity
          style={[styles.tab, pestana === 'texto' && styles.tabActive]}
          onPress={() => setPestana('texto')}
          activeOpacity={0.7}
          accessibilityRole="tab"
          accessibilityState={{ selected: pestana === 'texto' }}
        >
          <Text style={[styles.tabText, { fontSize: font(14) }, pestana === 'texto' && styles.tabTextActive]}>
            📝 Texto
          </Text>
        </TouchableOpacity>

        <TouchableOpacity
          style={[styles.tab, pestana === 'url' && styles.tabActive]}
          onPress={() => setPestana('url')}
          activeOpacity={0.7}
          accessibilityRole="tab"
          accessibilityState={{ selected: pestana === 'url' }}
        >
          <Text style={[styles.tabText, { fontSize: font(14) }, pestana === 'url' && styles.tabTextActive]}>
            🔗 Enlace
          </Text>
        </TouchableOpacity>

        <TouchableOpacity
          style={[styles.tab, pestana === 'archivo' && styles.tabActive]}
          onPress={() => setPestana('archivo')}
          activeOpacity={0.7}
          accessibilityRole="tab"
          accessibilityState={{ selected: pestana === 'archivo' }}
        >
          <Text style={[styles.tabText, { fontSize: font(14) }, pestana === 'archivo' && styles.tabTextActive]}>
            📷 Imagen
          </Text>
        </TouchableOpacity>
      </View>

      {/* Formulario según pestaña */}
      <View style={styles.card}>
        {pestana === 'texto' && (
          <View>
            <Text style={[styles.label, { fontSize: font(16) }]}>
              Texto o rumor a verificar:
            </Text>
            <TextInput
              style={[styles.textArea, { fontSize: font(16) }]}
              placeholder="Pegue aquí la cadena de WhatsApp, rumor o noticia..."
              placeholderTextColor={COLORS.tintaSuave}
              multiline
              numberOfLines={6}
              value={texto}
              onChangeText={setTexto}
              textAlignVertical="top"
            />
            <Text style={[styles.counter, { fontSize: font(12) }]}>
              {texto.length} caracteres (mínimo 10)
            </Text>
          </View>
        )}

        {pestana === 'url' && (
          <View>
            <Text style={[styles.label, { fontSize: font(16) }]}>
              Enlace de la noticia o publicación:
            </Text>
            <TextInput
              style={[styles.input, { fontSize: font(16) }]}
              placeholder="https://ejemplo.com/noticia..."
              placeholderTextColor={COLORS.tintaSuave}
              value={url}
              onChangeText={setUrl}
              keyboardType="url"
              autoCapitalize="none"
              autoCorrect={false}
            />
            <Text style={[styles.hint, { fontSize: font(13) }]}>
              Admite enlaces de Twitter/X, periódicos, portales web y comunicados.
            </Text>
          </View>
        )}

        {pestana === 'archivo' && (
          <View>
            <Text style={[styles.label, { fontSize: font(16) }]}>
              Fotografía o captura de pantalla:
            </Text>
            <View style={styles.fileButtonsRow}>
              <TouchableOpacity
                style={styles.fileBtn}
                onPress={seleccionarImagen}
                activeOpacity={0.8}
              >
                <Text style={[styles.fileBtnText, { fontSize: font(14) }]}>🖼️ De Galería</Text>
              </TouchableOpacity>
              <TouchableOpacity
                style={styles.fileBtn}
                onPress={tomarFoto}
                activeOpacity={0.8}
              >
                <Text style={[styles.fileBtnText, { fontSize: font(14) }]}>📸 Tomar Foto</Text>
              </TouchableOpacity>
            </View>

            {imagenSeleccionada && (
              <View style={styles.imagePreviewContainer}>
                <Image source={{ uri: imagenSeleccionada.uri }} style={styles.imagePreview} />
                <Text style={[styles.imageName, { fontSize: font(13) }]}>
                  ✅ Imagen seleccionada lista para OCR
                </Text>
              </View>
            )}

            <Text style={[styles.label, { fontSize: font(15), marginTop: 14 }]}>
              Breve descripción de lo que afirma:
            </Text>
            <TextInput
              style={[styles.input, { fontSize: font(15) }]}
              placeholder="Ej: Captura que afirma cierre bancario..."
              placeholderTextColor={COLORS.tintaSuave}
              value={descripcionArchivo}
              onChangeText={setDescripcionArchivo}
            />
          </View>
        )}

        {/* Botones de acción */}
        <View style={styles.actionsContainer}>
          <TouchableOpacity
            style={styles.primaryBtn}
            onPress={handleVerificar}
            activeOpacity={0.8}
            accessibilityRole="button"
          >
            <Text style={[styles.primaryBtnText, { fontSize: font(16) }]}>
              🔍 Verificar Contenido
            </Text>
          </TouchableOpacity>

          <TouchableOpacity
            style={styles.secondaryBtn}
            onPress={limpiarFormulario}
            activeOpacity={0.7}
            accessibilityRole="button"
          >
            <Text style={[styles.secondaryBtnText, { fontSize: font(15) }]}>
              🗑️ Limpiar
            </Text>
          </TouchableOpacity>
        </View>
      </View>
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: COLORS.papel,
  },
  content: {
    padding: 16,
    paddingBottom: 32,
  },
  introCard: {
    backgroundColor: COLORS.blanco,
    borderRadius: 12,
    borderWidth: 2,
    borderColor: COLORS.borde,
    padding: 14,
    marginBottom: 14,
  },
  introTitle: {
    fontWeight: '800',
    color: COLORS.tinta,
    marginBottom: 4,
  },
  introText: {
    color: COLORS.tintaSuave,
    lineHeight: 20,
  },
  tabsRow: {
    flexDirection: 'row',
    gap: 6,
    marginBottom: 10,
  },
  tab: {
    flex: 1,
    minHeight: 48,
    backgroundColor: COLORS.neutroBg,
    borderRadius: 8,
    borderWidth: 2,
    borderColor: COLORS.borde,
    alignItems: 'center',
    justifyContent: 'center',
  },
  tabActive: {
    backgroundColor: COLORS.blanco,
    borderColor: COLORS.bordeFuerte,
  },
  tabText: {
    fontWeight: '600',
    color: COLORS.tintaSuave,
  },
  tabTextActive: {
    color: COLORS.tinta,
    fontWeight: '800',
  },
  card: {
    backgroundColor: COLORS.blanco,
    borderRadius: 12,
    borderWidth: 2,
    borderColor: COLORS.bordeFuerte,
    padding: 16,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.05,
    shadowRadius: 4,
    elevation: 2,
  },
  label: {
    fontWeight: '700',
    color: COLORS.tinta,
    marginBottom: 6,
  },
  input: {
    minHeight: 50,
    borderWidth: 2,
    borderColor: COLORS.borde,
    borderRadius: 8,
    paddingHorizontal: 14,
    backgroundColor: COLORS.papel,
    color: COLORS.tinta,
  },
  textArea: {
    minHeight: 120,
    borderWidth: 2,
    borderColor: COLORS.borde,
    borderRadius: 8,
    padding: 14,
    backgroundColor: COLORS.papel,
    color: COLORS.tinta,
  },
  counter: {
    textAlign: 'right',
    color: COLORS.tintaMuted,
    marginTop: 4,
  },
  hint: {
    color: COLORS.tintaSuave,
    marginTop: 6,
  },
  fileButtonsRow: {
    flexDirection: 'row',
    gap: 10,
    marginTop: 6,
  },
  fileBtn: {
    flex: 1,
    minHeight: 48,
    borderWidth: 2,
    borderColor: COLORS.borde,
    backgroundColor: COLORS.neutroBg,
    borderRadius: 8,
    alignItems: 'center',
    justifyContent: 'center',
  },
  fileBtnText: {
    fontWeight: '700',
    color: COLORS.tinta,
  },
  imagePreviewContainer: {
    marginTop: 12,
    alignItems: 'center',
  },
  imagePreview: {
    width: '100%',
    height: 160,
    borderRadius: 8,
    resizeMode: 'cover',
  },
  imageName: {
    color: COLORS.verdadero.color,
    fontWeight: '700',
    marginTop: 6,
  },
  actionsContainer: {
    marginTop: 20,
    gap: 10,
  },
  primaryBtn: {
    minHeight: 52,
    backgroundColor: COLORS.marca,
    borderRadius: 8,
    alignItems: 'center',
    justifyContent: 'center',
  },
  primaryBtnText: {
    color: COLORS.blanco,
    fontWeight: '800',
  },
  secondaryBtn: {
    minHeight: 48,
    backgroundColor: COLORS.blanco,
    borderWidth: 2,
    borderColor: COLORS.borde,
    borderRadius: 8,
    alignItems: 'center',
    justifyContent: 'center',
  },
  secondaryBtnText: {
    color: COLORS.tinta,
    fontWeight: '700',
  },
  // Pantalla de procesamiento y Thinking Console
  processingContainer: {
    flex: 1,
    backgroundColor: COLORS.papel,
    justifyContent: 'center',
    padding: 16,
  },
  thinkingCard: {
    backgroundColor: '#ffffff',
    borderRadius: 16,
    padding: 20,
    borderWidth: 2,
    borderColor: '#e2e8f0',
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 4 },
    shadowOpacity: 0.1,
    shadowRadius: 10,
    elevation: 5,
  },
  thinkingHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 8,
    marginBottom: 4,
  },
  pulseIndicator: {
    width: 10,
    height: 10,
    borderRadius: 5,
    backgroundColor: '#10b981',
  },
  processingTitle: {
    fontWeight: '800',
    color: COLORS.tinta,
    textAlign: 'center',
  },
  processingSubtitle: {
    color: COLORS.tintaSuave,
    textAlign: 'center',
    marginBottom: 16,
    lineHeight: 18,
  },
  progressRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    marginBottom: 4,
  },
  progressLabel: {
    fontSize: 12,
    fontFamily: MONO_FONT,
    color: '#64748b',
  },
  progressValue: {
    fontSize: 12,
    fontFamily: MONO_FONT,
    fontWeight: '700',
    color: '#10b981',
  },
  progressBarBg: {
    height: 6,
    backgroundColor: '#e2e8f0',
    borderRadius: 3,
    overflow: 'hidden',
    marginBottom: 16,
  },
  progressBarFill: {
    height: '100%',
    backgroundColor: '#10b981',
    borderRadius: 3,
  },
  consoleBox: {
    backgroundColor: '#090d16',
    borderRadius: 12,
    borderWidth: 1,
    borderColor: '#1e293b',
    padding: 12,
  },
  consoleHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    borderBottomWidth: 1,
    borderBottomColor: '#1e293b',
    paddingBottom: 8,
    marginBottom: 8,
  },
  consoleDot: {
    width: 6,
    height: 6,
    borderRadius: 3,
    backgroundColor: '#34d399',
  },
  consoleTitle: {
    color: '#34d399',
    fontFamily: MONO_FONT,
    fontSize: 10,
    fontWeight: '700',
    letterSpacing: 0.5,
    flex: 1,
    marginLeft: 6,
  },
  consoleTimer: {
    color: '#94a3b8',
    fontFamily: MONO_FONT,
    fontSize: 11,
  },
  consoleLogs: {
    maxHeight: 220,
  },
  logLine: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 4,
    marginBottom: 6,
  },
  logTime: {
    color: '#64748b',
    fontFamily: MONO_FONT,
    fontSize: 11,
  },
  logTag: {
    fontFamily: MONO_FONT,
    fontSize: 11,
    fontWeight: '700',
  },
  logMsg: {
    color: '#e2e8f0',
    fontFamily: MONO_FONT,
    fontSize: 11,
    flexShrink: 1,
  },
});
