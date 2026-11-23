import React, { useState, useEffect, useCallback } from 'react';
import {
  View,
  Text,
  TextInput,
  TouchableOpacity,
  FlatList,
  ScrollView,
  StyleSheet,
  Linking,
  Alert,
} from 'react-native';
import { COLORS } from '../theme/colors';
import { useTheme } from '../context/ThemeContext';
import { useAuth } from '../context/AuthContext';
import { obtenerFuentes, valorarFuenteApi } from '../services/api';

const FUENTES_BASE = [
  {
    id_fuente: 'demo-f-1',
    nombre: 'ColombiaCheck',
    categoria: 'Fact-checking',
    nivel: 4,
    nivel_etiqueta: 'Nivel 4: Fact-Checker Certificado IFCN / Oficial de Control',
    indice_credibilidad: 98,
    url_sitio: 'https://colombiacheck.com',
    descripcion: 'Medio digital sin ánimo de lucro dedicado a la comprobación de datos e información pública.',
  },
  {
    id_fuente: 'demo-f-2',
    nombre: 'La Silla Vacía (Detector de Mentiras)',
    categoria: 'Fact-checking',
    nivel: 4,
    nivel_etiqueta: 'Nivel 4: Fact-Checker Certificado IFCN / Oficial de Control',
    indice_credibilidad: 96,
    url_sitio: 'https://lasillavacia.com',
    descripcion: 'Puntaje de rigor periodístico y verificación certificado por la Red Internacional de Fact-Checking.',
  },
  {
    id_fuente: 'demo-f-3',
    nombre: 'DANE (Estadísticas Oficiales)',
    categoria: 'Entidad Oficial',
    nivel: 4,
    nivel_etiqueta: 'Nivel 4: Fact-Checker Certificado IFCN / Oficial de Control',
    tipo_fuente: 'OFICIAL',
    indice_credibilidad: 99,
    url_sitio: 'https://dane.gov.co',
    descripcion: 'Entidad oficial encargada de las estadísticas, censos e índices socioeconómicos de Colombia.',
  },
  {
    id_fuente: 'demo-f-4',
    nombre: 'Ministerio de Salud e INVIMA',
    categoria: 'Entidad Oficial',
    nivel: 3,
    tipo_fuente: 'OFICIAL',
    nivel_etiqueta: 'Nivel 3: Autoridad Sanitaria / Oficial',
    indice_credibilidad: 97,
    url_sitio: 'https://minsalud.gov.co',
    descripcion: 'Autoridad rectora en alertas epidemiológicas, regulación de fármacos y salud pública.',
  },
  {
    id_fuente: 'demo-f-5',
    nombre: 'El Espectador',
    categoria: 'Medio Tradicional',
    nivel: 3,
    tipo_fuente: 'PERIODISTICO',
    nivel_etiqueta: 'Nivel 3: Prensa Nacional Colombia',
    indice_credibilidad: 88,
    url_sitio: 'https://elespectador.com',
    descripcion: 'Diario tradicional colombiano con cobertura nacional y mesas de verificación de redacción.',
  },
  {
    id_fuente: 'demo-f-6',
    nombre: 'RTVC Noticias',
    categoria: 'Medio Tradicional',
    nivel: 3,
    tipo_fuente: 'PERIODISTICO',
    nivel_etiqueta: 'Nivel 3: Prensa Nacional Colombia',
    indice_credibilidad: 89,
    url_sitio: 'https://rtvcnoticias.com',
    descripcion: 'Sistema informativo de la radio y televisión pública de Colombia.',
  },
];

function estiloCredibilidad(score) {
  if (score >= 75) return COLORS.verdadero;
  if (score >= 40) return COLORS.parcial;
  return COLORS.falso;
}

export default function FuentesScreen({ navigation }) {
  const { font } = useTheme();
  const { estaAutenticado } = useAuth();

  const [busqueda, setBusqueda] = useState('');
  const [categoria, setCategoria] = useState('TODAS');
  const [fuentes, setFuentes] = useState(FUENTES_BASE);
  const [cargando, setCargando] = useState(false);
  const [usandoFallback, setUsandoFallback] = useState(false);
  const [valoracionesLocales, setValoracionesLocales] = useState({});

  const cargarFuentes = useCallback(async () => {
    setCargando(true);
    try {
      const res = await obtenerFuentes();
      const datos = res?.fuentes || res?.data || (Array.isArray(res) ? res : []);
      if (Array.isArray(datos) && datos.length > 0) {
        setFuentes(datos);
        setUsandoFallback(false);
      }
    } catch {
      setFuentes(FUENTES_BASE);
      setUsandoFallback(true);
    } finally {
      setCargando(false);
    }
  }, []);

  useEffect(() => {
    cargarFuentes();
  }, [cargarFuentes]);

  const fuentesFiltradas = fuentes.filter((f) => {
    const texto = `${f.nombre || f.nombre_fuente || ''} ${f.clasificacion || ''} ${f.nivel_etiqueta || ''} ${f.tipo_fuente || ''} ${f.tipo || ''} ${f.descripcion || ''}`.toLowerCase();
    const coincideBusqueda = texto.includes(busqueda.trim().toLowerCase());
    if (!coincideBusqueda) return false;

    if (categoria === 'TODAS') return true;

    const meta = `${f.categoria || ''} ${f.clasificacion || ''} ${f.tipo_fuente || ''} ${f.nivel_etiqueta || ''} ${f.tipo || ''}`
      .normalize('NFD')
      .replace(/[\u0300-\u036f]/g, '')
      .toUpperCase();
    const nivel = Number(f.nivel || 0);

    if (categoria === 'FACT-CHECKING') {
      return nivel === 4 || meta.includes('FACT') || meta.includes('IFCN') || meta.includes('ACADEM');
    }
    if (categoria === 'OFICIAL') {
      return meta.includes('OFICIAL') || meta.includes('GUBERN') || meta.includes('CONTROL');
    }
    if (categoria === 'MEDIOS') {
      return (
        meta.includes('MEDIO') ||
        meta.includes('PERIODIST') ||
        meta.includes('PRENSA') ||
        nivel === 1 ||
        nivel === 2 ||
        nivel === 3
      );
    }
    return true;
  });

  const abrirEnlace = (url) => {
    if (url && (url.startsWith('http://') || url.startsWith('https://'))) {
      Linking.openURL(url).catch(() => {
        Alert.alert('Error', 'No se pudo abrir el sitio web.');
      });
    }
  };

  const handleValorar = async (fuente, valor) => {
    const idFuente = fuente.id_fuente || fuente.id;
    const esDemo = !idFuente || String(idFuente).startsWith('demo-');

    if (esDemo || usandoFallback) {
      Alert.alert('Modo demostración', 'Conéctese al servidor backend para registrar valoraciones reales.');
      return;
    }

    if (!estaAutenticado) {
      Alert.alert(
        'Iniciar Sesión Requerido',
        'Para calificar la confiabilidad de una fuente, inicie sesión en la pestaña "Mi cuenta".',
        [
          { text: 'Cancelar', style: 'cancel' },
          { text: 'Ir a Mi cuenta', onPress: () => navigation.navigate('Mi cuenta') },
        ]
      );
      return;
    }

    try {
      const res = await valorarFuenteApi(idFuente, valor);
      const nuevoIndice = Number(res?.indice_credibilidad ?? fuente.indice_credibilidad);
      setFuentes((prev) =>
        prev.map((item) =>
          (item.id_fuente || item.id) === idFuente
            ? { ...item, indice_credibilidad: nuevoIndice }
            : item
        )
      );
      setValoracionesLocales((prev) => ({ ...prev, [idFuente]: valor === 100 ? 'si' : 'no' }));
    } catch (err) {
      Alert.alert('No se pudo valorar', err?.message || 'Error al registrar su calificación.');
    }
  };

  const renderFuente = ({ item }) => {
    const scoreRaw = item.indice_credibilidad ?? item.confiabilidad ?? item.nivel_confianza ?? 50;
    const score = Math.max(0, Math.min(100, Math.round(Number(scoreRaw) || 0)));
    const estiloScore = estiloCredibilidad(score);
    const urlFuente = item.url_sitio || item.url;
    const idFuente = item.id_fuente || item.id || item.nombre;
    const voto = valoracionesLocales[idFuente];

    return (
      <View style={styles.card}>
        <View style={styles.cardHeader}>
          <View style={styles.titleWrap}>
            <Text style={[styles.fuenteNombre, { fontSize: font(17) }]}>
              {item.nivel === 1 ? '⭐ ' : '🏛️ '}
              {item.nombre || item.nombre_fuente}
            </Text>
            <Text style={[styles.fuenteTipo, { fontSize: font(12) }]}>
              {item.nivel_etiqueta || item.clasificacion || item.tipo || 'Fuente Contrastada'}
            </Text>
          </View>
          <View
            style={[
              styles.scoreBadge,
              { backgroundColor: estiloScore.bg, borderColor: estiloScore.borde },
            ]}
          >
            <Text style={[styles.scoreNumber, { fontSize: font(16), color: estiloScore.color }]}>
              {score}%
            </Text>
            <Text style={[styles.scoreLabel, { fontSize: font(10), color: estiloScore.color }]}>
              CONFIANZA
            </Text>
          </View>
        </View>

        <Text style={[styles.fuenteDesc, { fontSize: font(14) }]}>
          {item.descripcion ||
            `${item.clasificacion || 'Entidad'} integrada al Grafo de Credibilidad de DAGAZ para contrastar información pública.`}
        </Text>

        {urlFuente ? (
          <TouchableOpacity
            style={styles.linkBtn}
            onPress={() => abrirEnlace(urlFuente)}
            activeOpacity={0.7}
          >
            <Text style={[styles.linkBtnText, { fontSize: font(13) }]}>
              Visitar portal oficial ↗
            </Text>
          </TouchableOpacity>
        ) : null}

        {/* Valoración ciudadana CU07 */}
        <View style={styles.voteRow}>
          {voto ? (
            <Text style={[styles.votedText, { fontSize: font(13) }]}>
              ✔ Calificación guardada
            </Text>
          ) : (
            <>
              <Text style={[styles.votePrompt, { fontSize: font(12) }]}>Su opinión:</Text>
              <TouchableOpacity
                style={styles.voteBtn}
                onPress={() => handleValorar(item, 100)}
                activeOpacity={0.7}
              >
                <Text style={[styles.voteBtnText, { fontSize: font(12) }]}>👍 Confiable</Text>
              </TouchableOpacity>
              <TouchableOpacity
                style={styles.voteBtn}
                onPress={() => handleValorar(item, 0)}
                activeOpacity={0.7}
              >
                <Text style={[styles.voteBtnText, { fontSize: font(12) }]}>👎 No confiable</Text>
              </TouchableOpacity>
            </>
          )}
        </View>
      </View>
    );
  };

  return (
    <View style={styles.container}>
      {/* Cabecera explicativa */}
      <View style={styles.topInfo}>
        <Text style={[styles.topTitle, { fontSize: font(18) }]}>
          Grafo de Credibilidad DAGAZ
        </Text>
        <Text style={[styles.topSubtitle, { fontSize: font(13) }]}>
          {fuentesFiltradas.length} de {fuentes.length} fuentes y verificadores consultados
          {usandoFallback ? ' (catálogo local de muestra)' : ''}.
        </Text>
      </View>

      {/* Buscador */}
      <View style={styles.searchWrap}>
        <TextInput
          style={[styles.searchInput, { fontSize: font(15) }]}
          placeholder="🔍 Buscar fuente o entidad..."
          placeholderTextColor={COLORS.tintaSuave}
          value={busqueda}
          onChangeText={setBusqueda}
        />
      </View>

      {/* Filtros de categoría */}
      <View style={styles.categoriesWrap}>
        <ScrollView
          horizontal
          showsHorizontalScrollIndicator={false}
          contentContainerStyle={styles.categoriesRow}
        >
          {[
            { key: 'TODAS', label: 'Todas' },
            { key: 'FACT-CHECKING', label: 'Fact-checking / Control' },
            { key: 'OFICIAL', label: 'Oficiales' },
            { key: 'MEDIOS', label: 'Medios' },
          ].map((c) => {
            const activa = categoria === c.key;
            return (
              <TouchableOpacity
                key={c.key}
                style={[styles.categoryBtn, activa && styles.categoryBtnActive]}
                onPress={() => setCategoria(c.key)}
                activeOpacity={0.7}
              >
                <Text
                  style={[
                    styles.categoryText,
                    { fontSize: font(13) },
                    activa && styles.categoryTextActive,
                  ]}
                >
                  {c.label}
                </Text>
              </TouchableOpacity>
            );
          })}
        </ScrollView>
      </View>

      {/* Lista */}
      <FlatList
        data={fuentesFiltradas}
        keyExtractor={(item, idx) => String(item.id_fuente || item.id || item.nombre || idx)}
        renderItem={renderFuente}
        contentContainerStyle={styles.listContent}
        refreshing={cargando}
        onRefresh={cargarFuentes}
        ListEmptyComponent={
          !cargando ? (
            <View style={styles.emptyBox}>
              <Text style={[styles.emptyText, { fontSize: font(15) }]}>
                🔎 Ninguna fuente coincide con su búsqueda o filtro.
              </Text>
            </View>
          ) : null
        }
      />
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: COLORS.papel,
  },
  topInfo: {
    backgroundColor: COLORS.blanco,
    paddingHorizontal: 16,
    paddingTop: 14,
    paddingBottom: 8,
  },
  topTitle: {
    fontWeight: '800',
    color: COLORS.tinta,
  },
  topSubtitle: {
    color: COLORS.tintaSuave,
    marginTop: 2,
    lineHeight: 18,
  },
  searchWrap: {
    paddingHorizontal: 16,
    paddingVertical: 8,
    backgroundColor: COLORS.blanco,
  },
  searchInput: {
    minHeight: 46,
    borderWidth: 2,
    borderColor: COLORS.borde,
    borderRadius: 8,
    paddingHorizontal: 14,
    backgroundColor: COLORS.papel,
    color: COLORS.tinta,
  },
  categoriesWrap: {
    backgroundColor: COLORS.blanco,
    borderBottomWidth: 1,
    borderBottomColor: COLORS.borde,
  },
  categoriesRow: {
    flexDirection: 'row',
    paddingHorizontal: 16,
    paddingBottom: 10,
    gap: 8,
  },
  categoryBtn: {
    paddingHorizontal: 12,
    paddingVertical: 6,
    borderRadius: 20,
    borderWidth: 1,
    borderColor: COLORS.borde,
    backgroundColor: COLORS.papel,
  },
  categoryBtnActive: {
    backgroundColor: COLORS.marca,
    borderColor: COLORS.marca,
  },
  categoryText: {
    fontWeight: '700',
    color: COLORS.tintaSuave,
  },
  categoryTextActive: {
    color: COLORS.blanco,
  },
  listContent: {
    padding: 16,
    gap: 12,
    paddingBottom: 28,
  },
  card: {
    backgroundColor: COLORS.blanco,
    borderRadius: 12,
    borderWidth: 2,
    borderColor: COLORS.borde,
    padding: 14,
  },
  cardHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'flex-start',
  },
  titleWrap: {
    flex: 1,
    marginRight: 10,
  },
  fuenteNombre: {
    fontWeight: '800',
    color: COLORS.tinta,
  },
  fuenteTipo: {
    color: COLORS.tintaSuave,
    fontWeight: '600',
    marginTop: 2,
  },
  scoreBadge: {
    borderWidth: 1.5,
    borderRadius: 8,
    paddingHorizontal: 8,
    paddingVertical: 4,
    alignItems: 'center',
    minWidth: 68,
  },
  scoreNumber: {
    fontWeight: '800',
  },
  scoreLabel: {
    fontWeight: '800',
  },
  fuenteDesc: {
    color: COLORS.tinta,
    marginTop: 10,
    lineHeight: 20,
  },
  linkBtn: {
    marginTop: 10,
    alignSelf: 'flex-start',
  },
  linkBtnText: {
    color: '#2563EB',
    fontWeight: '700',
  },
  voteRow: {
    flexDirection: 'row',
    alignItems: 'center',
    flexWrap: 'wrap',
    gap: 8,
    marginTop: 12,
    paddingTop: 10,
    borderTopWidth: 1,
    borderTopColor: COLORS.borde,
  },
  votePrompt: {
    color: COLORS.tintaSuave,
    fontWeight: '600',
  },
  voteBtn: {
    paddingHorizontal: 10,
    paddingVertical: 6,
    borderRadius: 6,
    borderWidth: 1,
    borderColor: COLORS.borde,
    backgroundColor: COLORS.papel,
  },
  voteBtnText: {
    color: COLORS.tinta,
    fontWeight: '700',
  },
  votedText: {
    color: COLORS.verdadero.color,
    fontWeight: '700',
  },
  emptyBox: {
    padding: 28,
    alignItems: 'center',
  },
  emptyText: {
    color: COLORS.tintaSuave,
    textAlign: 'center',
    fontWeight: '600',
  },
});
