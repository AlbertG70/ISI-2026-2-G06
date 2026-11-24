import React, { useState, useEffect, useRef } from 'react';
import {
  View,
  Text,
  ScrollView,
  TouchableOpacity,
  TextInput,
  StyleSheet,
  Alert,
  Share,
  Linking,
  ActivityIndicator,
  AccessibilityInfo,
} from 'react-native';
import { COLORS, normalizarVeredicto } from '../theme/colors';
import { useTheme } from '../context/ThemeContext';
import { useAuth } from '../context/AuthContext';
import CircularGauge from '../components/CircularGauge';
import {
  obtenerVerificacion,
  guardarEnHistorial,
  agregarNotaComunitaria,
  calificarNota,
  listarNotas,
} from '../services/api';

const CARACTERES_INVALIDOS = /[<>{}\\\|]/;

function aliasAutor(email) {
  if (!email) return 'Ciudadano';
  const parte = String(email).split('@')[0] || 'Ciudadano';
  return parte.length > 16 ? `${parte.slice(0, 14)}…` : parte;
}

export default function ResultadoScreen({ route, navigation }) {
  const { font } = useTheme();
  const { estaAutenticado, usuario } = useAuth();

  const params = route.params || {};
  const idContenidoParam = params.idContenido || params.contenido?.id_contenido;

  const [contenido, setContenido] = useState(params.contenido || {});
  const [veredicto, setVeredicto] = useState(params.veredicto || null);
  const [fuentes, setFuentes] = useState(params.veredicto?.fuentes || params.fuentes || []);
  const [cargandoDetalle, setCargandoDetalle] = useState(false);

  const [mostrarMotivo, setMostrarMotivo] = useState(true);
  const [mostrarExplicacionCompleta, setMostrarExplicacionCompleta] = useState(false);
  const scrollRef = useRef(null);
  const tituloFuentesRef = useRef(null);
  const posicionFuentes = useRef(0);
  const [guardado, setGuardado] = useState(false);
  const [guardando, setGuardando] = useState(false);

  const [filtroNotas, setFiltroNotas] = useState('todas'); // 'todas' | 'utiles' | 'no_utiles'
  const [textoNuevaNota, setTextoNuevaNota] = useState('');
  const [enviandoNota, setEnviandoNota] = useState(false);
  const [notas, setNotas] = useState([]);
  const [votosLocales, setVotosLocales] = useState({});

  const esDemo = !idContenidoParam || String(idContenidoParam).startsWith('demo-');

  useEffect(() => {
    if (!esDemo && idContenidoParam) {
      cargarDetalleCompleto(idContenidoParam);
    } else if (esDemo) {
      setNotas([
        {
          id_nota: 'demo-n-1',
          autor: 'Adulto Verificador',
          fecha_creacion: new Date().toISOString(),
          contenido_nota:
            'Esta información coincide con el comunicado oficial emitido por la entidad desmintiendo la noticia.',
          calificacion: 8.0,
        },
        {
          id_nota: 'demo-n-2',
          autor: 'Ciudadano Alerta',
          fecha_creacion: new Date(Date.now() - 86400000).toISOString(),
          contenido_nota:
            'Es una cadena que circula desde 2021 y le cambian las fechas cada año para asustar.',
          calificacion: 9.0,
        },
      ]);
    }
  }, [idContenidoParam]);

  const cargarDetalleCompleto = async (id) => {
    setCargandoDetalle(true);
    try {
      const datos = await obtenerVerificacion(id);
      if (datos?.contenido) setContenido(datos.contenido);
      if (datos?.veredicto) {
        setVeredicto(datos.veredicto);
        if (Array.isArray(datos.veredicto.fuentes)) {
          setFuentes(datos.veredicto.fuentes);
        }
      }
      if (Array.isArray(datos?.notas)) {
        setNotas(datos.notas);
      }
    } catch {
      // Mantiene los datos iniciales pasados por navegación
    } finally {
      setCargandoDetalle(false);
    }
  };

  const vInfo = normalizarVeredicto(veredicto?.tipo_veredicto);
  const confianza = Number(veredicto?.nivel_confiabilidad) || 0;
  const motivo = veredicto?.motivo_breve?.trim() || null;
  const explicacionCompleta = veredicto?.explicacion || null;

  const fuentesValidas = (fuentes || []).filter(
    (f) => f && f.cita_contraste && String(f.cita_contraste).trim() !== ''
  );

  const handleGuardar = async () => {
    if (!estaAutenticado) {
      Alert.alert(
        'Iniciar Sesión Requerido',
        'Para guardar veredictos en su historial personal, inicie sesión o cree una cuenta en la pestaña "Mi cuenta".',
        [
          { text: 'Entendido', style: 'cancel' },
          { text: 'Ir a Mi cuenta', onPress: () => navigation.navigate('Mi cuenta') },
        ]
      );
      return;
    }
    setGuardando(true);
    try {
      if (!esDemo && idContenidoParam) {
        const r = await guardarEnHistorial(idContenidoParam);
        setGuardado(true);
        Alert.alert('Guardado', r?.mensaje || 'La noticia fue guardada exitosamente en su historial.');
      } else {
        setGuardado(true);
        Alert.alert('Guardado', 'Noticia de muestra agregada.');
      }
    } catch (err) {
      Alert.alert('Error al guardar', err.message || 'No se pudo guardar en el historial.');
    } finally {
      setGuardando(false);
    }
  };

  const handleCompartir = async () => {
    const textoCompartir = `🔍 Verificación DAGAZ:\n\nNoticia: "${contenido?.contenido_original || contenido?.url_origen || 'Noticia'}"\n\n⚖️ Veredicto: ${vInfo.etiqueta.toUpperCase()} (${Math.round(confianza)}% de certeza)\n${vInfo.resumen}\n\nVerificado en la plataforma ciudadana DAGAZ.`;
    try {
      await Share.share({ message: textoCompartir });
    } catch {}
  };

  const irAFuentes = () => {
    scrollRef.current?.scrollTo({ y: posicionFuentes.current, animated: false });
    requestAnimationFrame(() => {
      if (tituloFuentesRef.current) {
        AccessibilityInfo.sendAccessibilityEvent?.(tituloFuentesRef.current, 'focus');
      }
    });
  };

  const handleAbrirFuente = (url) => {
    if (url && (url.startsWith('http://') || url.startsWith('https://'))) {
      Linking.openURL(url).catch(() => {
        Alert.alert('Error', 'No se pudo abrir el enlace.');
      });
    }
  };

  const handleCalificarNota = async (nota, esUtil) => {
    if (!esDemo && !estaAutenticado) {
      Alert.alert(
        'Iniciar Sesión Requerido',
        'Para calificar notas comunitarias, inicie sesión en la pestaña "Mi cuenta".',
        [
          { text: 'Entendido', style: 'cancel' },
          { text: 'Ir a Mi cuenta', onPress: () => navigation.navigate('Mi cuenta') },
        ]
      );
      return;
    }

    const idNota = nota.id_nota;
    const valor = esUtil ? 10 : 0;

    if (esDemo) {
      setVotosLocales((prev) => ({ ...prev, [idNota]: esUtil ? 'si' : 'no' }));
      setNotas((prev) =>
        prev.map((n) =>
          n.id_nota === idNota
            ? { ...n, calificacion: Number(((Number(n.calificacion || 0) + valor) / 2).toFixed(1)) }
            : n
        )
      );
      return;
    }

    try {
      const r = await calificarNota(idNota, valor);
      const nuevaCal = Number(r?.calificacion_actual ?? valor);
      setVotosLocales((prev) => ({ ...prev, [idNota]: esUtil ? 'si' : 'no' }));
      setNotas((prev) =>
        prev.map((n) => (n.id_nota === idNota ? { ...n, calificacion: nuevaCal } : n))
      );
      Alert.alert('Calificación registrada', '¡Gracias! Su opinión ayuda a otros ciudadanos.');
    } catch (err) {
      Alert.alert('Aviso', err.message || 'No se pudo registrar la calificación.');
    }
  };

  const handleEnviarNota = async () => {
    const texto = textoNuevaNota.trim();
    if (texto.length < 20) {
      Alert.alert('Nota muy corta', 'Escriba al menos 20 caracteres aportando contexto verificable.');
      return;
    }
    if (CARACTERES_INVALIDOS.test(texto)) {
      Alert.alert('Caracteres no permitidos', 'La nota contiene caracteres no permitidos (< > { } \\ |).');
      return;
    }

    if (!esDemo && !estaAutenticado) {
      Alert.alert(
        'Iniciar Sesión Requerido',
        'Para publicar una nota comunitaria, inicie sesión en la pestaña "Mi cuenta".',
        [
          { text: 'Entendido', style: 'cancel' },
          { text: 'Ir a Mi cuenta', onPress: () => navigation.navigate('Mi cuenta') },
        ]
      );
      return;
    }

    setEnviandoNota(true);
    try {
      if (esDemo) {
        const nueva = {
          id_nota: `demo-n-${Date.now()}`,
          autor: usuario?.email || 'Usted',
          fecha_creacion: new Date().toISOString(),
          contenido_nota: texto,
          calificacion: 10.0,
        };
        setNotas((prev) => [nueva, ...prev]);
      } else {
        await agregarNotaComunitaria(idContenidoParam, texto);
        const resNotas = await listarNotas(idContenidoParam);
        if (Array.isArray(resNotas?.notas)) {
          setNotas(resNotas.notas);
        }
      }
      setTextoNuevaNota('');
      Alert.alert('Nota publicada', '¡Gracias por aportar al fact-checking comunitario!');
    } catch (err) {
      Alert.alert('No se pudo publicar', err.message || 'Error al guardar la nota.');
    } finally {
      setEnviandoNota(false);
    }
  };

  const notasFiltradas = notas.filter((n) => {
    const cal = Number(n.calificacion || 0);
    if (filtroNotas === 'utiles') return cal >= 6;
    if (filtroNotas === 'no_utiles') return cal < 6;
    return true;
  });

  return (
    <ScrollView ref={scrollRef} style={styles.container} contentContainerStyle={styles.content}>
      {/* Botón Volver */}
      <TouchableOpacity
        style={styles.backBtn}
        onPress={() => navigation.goBack()}
        activeOpacity={0.7}
      >
        <Text style={[styles.backBtnText, { fontSize: font(15) }]}>
          ← Volver
        </Text>
      </TouchableOpacity>

      {/* Contenido analizado */}
      <View style={styles.quoteCard}>
        <Text style={[styles.quoteLabel, { fontSize: font(12) }]}>
          CONTENIDO VERIFICADO
        </Text>
        <Text style={[styles.quoteText, { fontSize: font(17) }]}>
          “{contenido?.contenido_original || contenido?.url_origen || 'Texto verificado'}”
        </Text>
        <Text style={[styles.quoteDate, { fontSize: font(13) }]}>
          Emitido el{' '}
          {new Date(veredicto?.fecha_emision || contenido?.fecha_subida || Date.now()).toLocaleDateString(
            'es-CO',
            {
              year: 'numeric',
              month: 'long',
              day: 'numeric',
            }
          )}
        </Text>
      </View>

      {cargandoDetalle && !veredicto ? (
        <View style={styles.loadingCard}>
          <ActivityIndicator size="large" color={COLORS.marca} />
          <Text style={[styles.loadingText, { fontSize: font(14) }]}>
            Cargando detalle completo del veredicto...
          </Text>
        </View>
      ) : null}

      {/* Tarjeta de Veredicto Principal */}
      {veredicto ? (
        <View style={[styles.verdictCard, { borderColor: vInfo.borde, backgroundColor: vInfo.bg }]}>
          <View style={styles.verdictTopRow}>
            <View style={styles.verdictBadgeWrap}>
              <Text style={styles.verdictBigIcon}>{vInfo.icono}</Text>
              <View style={styles.verdictTextCol}>
                <Text style={[styles.verdictLabel, { color: vInfo.color, fontSize: font(22) }]}>
                  {vInfo.etiqueta}
                </Text>
                <Text style={[styles.verdictSummary, { fontSize: font(14) }]}>
                  {vInfo.resumen}
                </Text>
              </View>
            </View>
            <CircularGauge porcentaje={confianza} color={vInfo.color} size={70} />
          </View>

          {motivo && (
            <TouchableOpacity
              style={styles.accordionHeader}
              onPress={() => setMostrarMotivo((abierto) => !abierto)}
              accessibilityRole="button"
              accessibilityState={{ expanded: mostrarMotivo }}
            >
              <Text style={[styles.accordionTitle, { fontSize: font(18) }]}>
                ¿Por qué este veredicto?
              </Text>
            </TouchableOpacity>
          )}

          {(!motivo || mostrarMotivo) && (motivo || explicacionCompleta || fuentesValidas.length > 0) && (
            <View style={styles.accordionBody}>
              {motivo && (
                <Text style={[styles.accordionText, { fontSize: font(18), lineHeight: font(26) }]}>
                  {motivo}
                </Text>
              )}
              {fuentesValidas.length > 0 && (
                <TouchableOpacity
                  style={styles.accordionAction}
                  onPress={irAFuentes}
                  accessibilityRole="link"
                >
                  <Text style={[styles.accordionTitle, styles.enlaceFuentes, { fontSize: font(18) }]}>
                    Ver fuentes consultadas
                  </Text>
                </TouchableOpacity>
              )}
              {explicacionCompleta && (
                <>
                  <TouchableOpacity
                    style={styles.accordionAction}
                    onPress={() => setMostrarExplicacionCompleta((abierta) => !abierta)}
                    accessibilityRole="button"
                    accessibilityState={{ expanded: mostrarExplicacionCompleta }}
                  >
                    <Text style={[styles.accordionTitle, { fontSize: font(18) }]}>
                      {mostrarExplicacionCompleta ? 'Ocultar explicación completa' : 'Ver explicación completa'}
                    </Text>
                  </TouchableOpacity>
                  {mostrarExplicacionCompleta && (
                    <Text style={[styles.accordionText, { fontSize: font(18), lineHeight: font(26) }]}>
                      {explicacionCompleta}
                    </Text>
                  )}
                </>
              )}
            </View>
          )}

          {/* Badge intervención humana */}
          {(veredicto?.explicacion?.includes('[Revisión Editorial') || veredicto?.intervenido_por) && (
            <View style={styles.editorialBadge}>
              <Text style={[styles.editorialBadgeText, { fontSize: font(12) }]}>
                ✍️ Veredicto Intervenido y Rectificado por Revisor Humano
              </Text>
            </View>
          )}

          {/* Alerta confianza baja */}
          {confianza < 60 && (
            <View style={styles.lowConfidenceAlert}>
              <Text style={[styles.lowConfidenceText, { fontSize: font(13) }]}>
                ⚠️ Confianza baja: este resultado puede ser revisado por un editor humano. Compártalo con precaución.
              </Text>
            </View>
          )}
        </View>
      ) : null}

      {/* Botones de acción directa: Guardar y Compartir */}
      <View style={styles.actionButtonsRow}>
        <TouchableOpacity
          style={[styles.saveBtn, guardado && styles.saveBtnActive]}
          onPress={handleGuardar}
          activeOpacity={0.8}
          disabled={guardando || guardado}
        >
          <Text
            style={[
              styles.saveBtnText,
              { fontSize: font(15) },
              guardado && styles.saveBtnTextActive,
            ]}
          >
            {guardado ? '✓ Guardado en Historial' : '💾 Guardar en Historial'}
          </Text>
        </TouchableOpacity>

        <TouchableOpacity
          style={styles.shareBtn}
          onPress={handleCompartir}
          activeOpacity={0.8}
        >
          <Text style={[styles.shareBtnText, { fontSize: font(15) }]}>
            📤 Compartir
          </Text>
        </TouchableOpacity>
      </View>

      {/* Fuentes de Contraste (CU07) */}
      <View
        style={styles.sectionCard}
        onLayout={({ nativeEvent }) => { posicionFuentes.current = nativeEvent.layout.y; }}
      >
        <Text ref={tituloFuentesRef} accessibilityRole="header" style={[styles.sectionTitle, { fontSize: font(18) }]}>
          📰 Fuentes de Contraste Consultadas
        </Text>
        <Text style={[styles.sectionSubtitle, { fontSize: font(13) }]}>
          Evidencia extraída del Grafo de Credibilidad y Consejo de IA:
        </Text>

        {fuentesValidas.length > 0 ? (
          fuentesValidas.map((f, i) => {
            const nombreFuente = f.nombre || f.nombre_fuente || 'Fuente Confiable';
            const urlFuente = f.url_sitio || f.url;
            const credibilidad = f.indice_credibilidad ?? f.nivel_confianza;
            return (
              <View key={f.id_fuente || `${nombreFuente}-${i}`} style={styles.fuenteItem}>
                <View style={styles.fuenteHeader}>
                  <Text style={[styles.fuenteNombre, { fontSize: font(15) }]}>
                    🏛️ {nombreFuente}
                  </Text>
                  {credibilidad !== null && credibilidad !== undefined ? (
                    <Text style={[styles.fuenteScore, { fontSize: font(12) }]}>
                      {Math.round(Number(credibilidad))}% confianza
                    </Text>
                  ) : null}
                </View>

                {f.modelo_evaluador ? (
                  <View style={styles.iaFuenteTag}>
                    <Text style={styles.iaFuenteTagText}>
                      🤖 Citada por {f.modelo_evaluador}
                    </Text>
                  </View>
                ) : null}

                <Text style={[styles.fuenteCita, { fontSize: font(14) }]}>
                  «{f.cita_contraste}»
                </Text>

                {urlFuente ? (
                  <TouchableOpacity
                    onPress={() => handleAbrirFuente(urlFuente)}
                    style={styles.fuenteEnlaceBtn}
                  >
                    <Text style={[styles.fuenteEnlaceText, { fontSize: font(13) }]}>
                      Abrir fuente original ↗
                    </Text>
                  </TouchableOpacity>
                ) : null}
              </View>
            );
          })
        ) : (
          <View style={styles.warningFuentesBox}>
            <Text style={[styles.warningFuentesTitle, { fontSize: font(14) }]}>
              No hay citas de fuentes disponibles para esta verificación.
            </Text>
          </View>
        )}
      </View>

      {/* Notas Comunitarias (CU01 / CU02) */}
      <View style={styles.sectionCard}>
        <Text style={[styles.sectionTitle, { fontSize: font(18) }]}>
          👥 Notas Comunitarias Ciudadanas ({notas.length})
        </Text>
        <Text style={[styles.sectionSubtitle, { fontSize: font(13) }]}>
          Contexto adicional redactado y calificado por la comunidad:
        </Text>

        {/* Filtros de notas */}
        <View style={styles.filterPillsRow}>
          <TouchableOpacity
            style={[styles.filterPill, filtroNotas === 'todas' && styles.filterPillActive]}
            onPress={() => setFiltroNotas('todas')}
          >
            <Text style={[styles.filterPillText, filtroNotas === 'todas' && styles.filterPillTextActive]}>
              Todas ({notas.length})
            </Text>
          </TouchableOpacity>

          <TouchableOpacity
            style={[styles.filterPill, filtroNotas === 'utiles' && styles.filterPillActive]}
            onPress={() => setFiltroNotas('utiles')}
          >
            <Text style={[styles.filterPillText, filtroNotas === 'utiles' && styles.filterPillTextActive]}>
              👍 Útiles
            </Text>
          </TouchableOpacity>

          <TouchableOpacity
            style={[styles.filterPill, filtroNotas === 'no_utiles' && styles.filterPillActive]}
            onPress={() => setFiltroNotas('no_utiles')}
          >
            <Text style={[styles.filterPillText, filtroNotas === 'no_utiles' && styles.filterPillTextActive]}>
              👎 No útiles
            </Text>
          </TouchableOpacity>
        </View>

        {/* Lista de notas */}
        {notasFiltradas.length > 0 ? (
          notasFiltradas.map((nota) => {
            const cal = Number(nota.calificacion || 0);
            const pct = Math.min(100, Math.max(0, Math.round((cal / 10) * 100)));
            const esPropia = usuario?.email && nota.autor === usuario.email;
            const voto = votosLocales[nota.id_nota];

            return (
              <View key={nota.id_nota} style={styles.notaCard}>
                <View style={styles.notaHeader}>
                  <Text style={[styles.notaAutor, { fontSize: font(13) }]}>
                    👤 {esPropia ? 'Usted' : aliasAutor(nota.autor)} ·{' '}
                    {nota.fecha_creacion
                      ? new Date(nota.fecha_creacion).toLocaleDateString('es-CO', {
                          day: 'numeric',
                          month: 'short',
                        })
                      : 'Reciente'}
                  </Text>
                </View>
                <Text style={[styles.notaTexto, { fontSize: font(15) }]}>
                  {nota.contenido_nota}
                </Text>

                {/* Barra de progreso de utilidad */}
                <View style={styles.utilityBarWrap}>
                  <View style={styles.utilityBarBg}>
                    <View style={[styles.utilityBarFill, { width: `${pct}%` }]} />
                  </View>
                  <Text style={[styles.utilityBarLabel, { fontSize: font(12) }]}>
                    {cal.toFixed(1)} de 10 adultos la encontraron útil ({pct}%)
                  </Text>
                </View>

                {/* Votación */}
                {!esPropia ? (
                  <View style={styles.voteRow}>
                    <Text style={[styles.votePrompt, { fontSize: font(13) }]}>¿Le fue útil?</Text>
                    <TouchableOpacity
                      style={[styles.voteBtn, voto === 'si' && styles.voteBtnActive]}
                      onPress={() => handleCalificarNota(nota, true)}
                    >
                      <Text style={[styles.voteBtnText, voto === 'si' && styles.voteBtnTextActive]}>
                        👍 Sí
                      </Text>
                    </TouchableOpacity>
                    <TouchableOpacity
                      style={[styles.voteBtn, voto === 'no' && styles.voteBtnActive]}
                      onPress={() => handleCalificarNota(nota, false)}
                    >
                      <Text style={[styles.voteBtnText, voto === 'no' && styles.voteBtnTextActive]}>
                        👎 No
                      </Text>
                    </TouchableOpacity>
                  </View>
                ) : (
                  <Text style={[styles.ownNoteLabel, { fontSize: font(12) }]}>
                    Nota de su autoría
                  </Text>
                )}
              </View>
            );
          })
        ) : (
          <Text style={[styles.emptyText, { fontSize: font(14), marginBottom: 10 }]}>
            {notas.length === 0
              ? 'Aún no hay notas comunitarias. ¡Sea el primero en aportar contexto!'
              : 'Ninguna nota coincide con este filtro.'}
          </Text>
        )}

        {/* Formulario para agregar nueva nota */}
        <View style={styles.addNoteContainer}>
          <Text style={[styles.addNoteTitle, { fontSize: font(15) }]}>
            ✍️ Agregar una nota de contexto:
          </Text>
          <TextInput
            style={[styles.addNoteInput, { fontSize: font(15) }]}
            placeholder="Aporte datos objetivos (mínimo 20 caracteres)..."
            placeholderTextColor={COLORS.tintaSuave}
            multiline
            numberOfLines={3}
            value={textoNuevaNota}
            onChangeText={setTextoNuevaNota}
            textAlignVertical="top"
          />
          <Text style={[styles.noteCounterText, { fontSize: font(12) }]}>
            {textoNuevaNota.trim().length} caracteres (mínimo 20)
          </Text>
          <TouchableOpacity
            style={styles.addNoteBtn}
            onPress={handleEnviarNota}
            disabled={enviandoNota}
            activeOpacity={0.8}
          >
            <Text style={[styles.addNoteBtnText, { fontSize: font(14) }]}>
              {enviandoNota ? 'Publicando…' : 'Publicar Nota Comunitaria'}
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
    paddingBottom: 40,
    gap: 16,
  },
  backBtn: {
    minHeight: 44,
    justifyContent: 'center',
  },
  backBtnText: {
    fontWeight: '700',
    color: COLORS.marca,
  },
  loadingCard: {
    backgroundColor: COLORS.blanco,
    borderRadius: 12,
    borderWidth: 2,
    borderColor: COLORS.borde,
    padding: 20,
    alignItems: 'center',
    gap: 10,
  },
  loadingText: {
    color: COLORS.tintaSuave,
    fontWeight: '600',
  },
  quoteCard: {
    backgroundColor: COLORS.blanco,
    borderRadius: 10,
    borderWidth: 2,
    borderColor: COLORS.borde,
    borderLeftWidth: 6,
    borderLeftColor: COLORS.marca,
    padding: 14,
  },
  quoteLabel: {
    fontWeight: '800',
    color: COLORS.tintaSuave,
    letterSpacing: 0.8,
  },
  quoteText: {
    fontWeight: '600',
    color: COLORS.tinta,
    marginTop: 4,
    lineHeight: 22,
  },
  quoteDate: {
    color: COLORS.tintaMuted,
    marginTop: 6,
  },
  verdictCard: {
    borderRadius: 14,
    borderWidth: 3,
    padding: 16,
  },
  verdictTopRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
  },
  verdictBadgeWrap: {
    flex: 1,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
    marginRight: 10,
  },
  verdictTextCol: {
    flex: 1,
  },
  verdictBigIcon: {
    fontSize: 40,
  },
  verdictLabel: {
    fontWeight: '800',
  },
  verdictSummary: {
    color: COLORS.tinta,
    marginTop: 2,
    fontWeight: '500',
  },
  accordionHeader: {
    minHeight: 48,
    justifyContent: 'center',
    marginTop: 14,
    paddingVertical: 8,
    borderTopWidth: 1,
    borderTopColor: COLORS.borde,
  },
  accordionAction: {
    minHeight: 48,
    justifyContent: 'center',
    paddingVertical: 8,
  },
  enlaceFuentes: {
    textDecorationLine: 'underline',
  },
  accordionTitle: {
    fontWeight: '700',
    color: COLORS.tinta,
  },
  accordionBody: {
    backgroundColor: COLORS.blanco,
    padding: 12,
    borderRadius: 8,
    marginTop: 6,
  },
  accordionText: {
    color: COLORS.tinta,
  },
  editorialBadge: {
    backgroundColor: COLORS.marca,
    paddingHorizontal: 10,
    paddingVertical: 5,
    borderRadius: 6,
    marginTop: 12,
    alignSelf: 'flex-start',
  },
  editorialBadgeText: {
    color: COLORS.blanco,
    fontWeight: '700',
  },
  lowConfidenceAlert: {
    backgroundColor: COLORS.parcial.bg,
    borderWidth: 1,
    borderColor: COLORS.parcial.borde,
    padding: 8,
    borderRadius: 6,
    marginTop: 10,
  },
  lowConfidenceText: {
    color: COLORS.parcial.color,
    fontWeight: '600',
  },
  actionButtonsRow: {
    flexDirection: 'row',
    gap: 10,
  },
  saveBtn: {
    flex: 1,
    minHeight: 50,
    backgroundColor: COLORS.blanco,
    borderWidth: 2,
    borderColor: COLORS.bordeFuerte,
    borderRadius: 8,
    alignItems: 'center',
    justifyContent: 'center',
  },
  saveBtnActive: {
    backgroundColor: COLORS.verdadero.bg,
    borderColor: COLORS.verdadero.color,
  },
  saveBtnText: {
    fontWeight: '700',
    color: COLORS.tinta,
  },
  saveBtnTextActive: {
    color: COLORS.verdadero.color,
  },
  shareBtn: {
    minHeight: 50,
    paddingHorizontal: 18,
    backgroundColor: COLORS.marca,
    borderRadius: 8,
    alignItems: 'center',
    justifyContent: 'center',
  },
  shareBtnText: {
    fontWeight: '700',
    color: COLORS.blanco,
  },
  sectionCard: {
    backgroundColor: COLORS.blanco,
    borderRadius: 12,
    borderWidth: 2,
    borderColor: COLORS.borde,
    padding: 16,
  },
  sectionTitle: {
    fontWeight: '800',
    color: COLORS.tinta,
  },
  sectionSubtitle: {
    color: COLORS.tintaSuave,
    marginTop: 2,
    marginBottom: 12,
  },
  fuenteItem: {
    borderTopWidth: 1,
    borderTopColor: COLORS.borde,
    paddingVertical: 10,
  },
  fuenteHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    gap: 8,
  },
  fuenteNombre: {
    flex: 1,
    fontWeight: '700',
    color: COLORS.tinta,
  },
  fuenteScore: {
    fontWeight: '700',
    color: COLORS.verdadero.color,
  },
  iaFuenteTag: {
    backgroundColor: '#DBEAFE',
    borderRadius: 12,
    paddingHorizontal: 8,
    paddingVertical: 2,
    alignSelf: 'flex-start',
    marginTop: 4,
  },
  iaFuenteTagText: {
    color: '#1E40AF',
    fontSize: 11,
    fontWeight: '700',
  },
  fuenteCita: {
    color: COLORS.tintaSuave,
    fontStyle: 'italic',
    marginTop: 6,
    lineHeight: 20,
  },
  fuenteEnlaceBtn: {
    marginTop: 6,
    alignSelf: 'flex-start',
  },
  fuenteEnlaceText: {
    color: '#2563EB',
    fontWeight: '600',
  },
  warningFuentesBox: {
    backgroundColor: COLORS.parcial.bg,
    borderWidth: 1.5,
    borderColor: COLORS.parcial.borde,
    borderRadius: 8,
    padding: 12,
  },
  warningFuentesTitle: {
    color: COLORS.parcial.color,
    fontWeight: '600',
    lineHeight: 20,
  },
  emptyText: {
    color: COLORS.tintaMuted,
    fontStyle: 'italic',
  },
  filterPillsRow: {
    flexDirection: 'row',
    gap: 8,
    marginBottom: 12,
  },
  filterPill: {
    paddingHorizontal: 12,
    paddingVertical: 6,
    borderRadius: 16,
    borderWidth: 1,
    borderColor: COLORS.borde,
    backgroundColor: COLORS.papel,
  },
  filterPillActive: {
    backgroundColor: COLORS.marca,
    borderColor: COLORS.marca,
  },
  filterPillText: {
    fontWeight: '600',
    color: COLORS.tintaSuave,
    fontSize: 12,
  },
  filterPillTextActive: {
    color: COLORS.blanco,
  },
  notaCard: {
    backgroundColor: COLORS.papel,
    borderRadius: 8,
    borderWidth: 1,
    borderColor: COLORS.borde,
    padding: 12,
    marginBottom: 10,
  },
  notaHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    marginBottom: 4,
  },
  notaAutor: {
    color: COLORS.tintaSuave,
    fontWeight: '600',
  },
  notaTexto: {
    color: COLORS.tinta,
    lineHeight: 20,
  },
  utilityBarWrap: {
    marginTop: 8,
  },
  utilityBarBg: {
    height: 6,
    backgroundColor: COLORS.borde,
    borderRadius: 3,
    overflow: 'hidden',
  },
  utilityBarFill: {
    height: '100%',
    backgroundColor: COLORS.verdadero.color,
  },
  utilityBarLabel: {
    marginTop: 3,
    color: COLORS.tintaMuted,
    fontWeight: '600',
  },
  voteRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
    marginTop: 10,
  },
  votePrompt: {
    color: COLORS.tintaSuave,
    fontWeight: '600',
  },
  voteBtn: {
    paddingHorizontal: 12,
    paddingVertical: 6,
    borderRadius: 6,
    borderWidth: 1,
    borderColor: COLORS.borde,
    backgroundColor: COLORS.blanco,
  },
  voteBtnActive: {
    backgroundColor: COLORS.marca,
    borderColor: COLORS.marca,
  },
  voteBtnText: {
    fontWeight: '700',
    fontSize: 12,
    color: COLORS.tinta,
  },
  voteBtnTextActive: {
    color: COLORS.blanco,
  },
  ownNoteLabel: {
    marginTop: 8,
    color: COLORS.tintaMuted,
    fontWeight: '600',
  },
  addNoteContainer: {
    borderTopWidth: 1,
    borderTopColor: COLORS.borde,
    paddingTop: 14,
    marginTop: 8,
  },
  addNoteTitle: {
    fontWeight: '700',
    color: COLORS.tinta,
    marginBottom: 6,
  },
  addNoteInput: {
    borderWidth: 1,
    borderColor: COLORS.borde,
    borderRadius: 8,
    padding: 10,
    backgroundColor: COLORS.papel,
    minHeight: 70,
    color: COLORS.tinta,
  },
  noteCounterText: {
    textAlign: 'right',
    color: COLORS.tintaMuted,
    marginTop: 4,
  },
  addNoteBtn: {
    marginTop: 8,
    minHeight: 44,
    backgroundColor: COLORS.marca,
    borderRadius: 8,
    alignItems: 'center',
    justifyContent: 'center',
  },
  addNoteBtnText: {
    color: COLORS.blanco,
    fontWeight: '700',
  },
});
