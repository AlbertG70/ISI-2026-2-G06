import React, { useState, useEffect, useCallback } from 'react';
import {
  View,
  Text,
  TextInput,
  TouchableOpacity,
  FlatList,
  ScrollView,
  StyleSheet,
  RefreshControl,
} from 'react-native';
import { useFocusEffect } from '@react-navigation/native';
import { COLORS, normalizarVeredicto } from '../theme/colors';
import { useTheme } from '../context/ThemeContext';
import { useAuth } from '../context/AuthContext';
import VerdictBadge from '../components/VerdictBadge';
import { obtenerHistorial } from '../services/api';

const ITEMS_MUESTRA = [
  {
    id_contenido: 'demo-1',
    resumen_contenido: 'Afirman que el agua embotellada de cierta marca contiene químicos tóxicos prohibidos.',
    fecha_evento: new Date(Date.now() - 86400000 * 2).toISOString(),
    guardado: true,
    veredicto: {
      tipo_veredicto: 'FALSO',
      nivel_confiabilidad: 94,
      explicacion: 'El INVIMA y las autoridades sanitarias confirmaron que los lotes cumplen los registros de pureza vigentes.',
    },
  },
  {
    id_contenido: 'demo-2',
    resumen_contenido: 'Decreto ministerial amplía los plazos de renovación de licencias de conducción en 2026.',
    fecha_evento: new Date(Date.now() - 86400000 * 5).toISOString(),
    guardado: false,
    veredicto: {
      tipo_veredicto: 'VERDADERO',
      nivel_confiabilidad: 98,
      explicacion: 'Publicado formalmente en la gaceta nacional y confirmado por el Ministerio de Transporte.',
    },
  },
  {
    id_contenido: 'demo-3',
    resumen_contenido: 'Nueva ley exigirá pagar impuesto por el uso de aplicaciones de mensajería en el celular.',
    fecha_evento: new Date(Date.now() - 86400000 * 8).toISOString(),
    guardado: false,
    veredicto: {
      tipo_veredicto: 'ENGANOSO',
      nivel_confiabilidad: 88,
      explicacion: 'El proyecto de reforma contempla gravámenes a plataformas extranjeras de apuestas y streaming, pero no al usuario por mensajería.',
    },
  },
];

function agruparRegistros(lista) {
  const mapa = new Map();
  (lista || []).forEach((r) => {
    const id = r.id_contenido || r.id;
    if (!id) return;
    const previo = mapa.get(id);
    if (!previo) {
      mapa.set(id, {
        ...r,
        guardado: Boolean(r.guardado) || r.accion === 'guardado',
      });
    } else {
      mapa.set(id, {
        ...previo,
        ...r,
        guardado: previo.guardado || Boolean(r.guardado) || r.accion === 'guardado',
        fecha_evento: r.fecha_evento || previo.fecha_evento,
        veredicto: r.veredicto || previo.veredicto,
      });
    }
  });
  return Array.from(mapa.values());
}

export default function HistorialScreen({ navigation }) {
  const { font } = useTheme();
  const { estaAutenticado } = useAuth();

  const [busqueda, setBusqueda] = useState('');
  const [filtroVeredicto, setFiltroVeredicto] = useState('TODOS');
  const [items, setItems] = useState([]);
  const [cargando, setCargando] = useState(false);
  const [usandoDemo, setUsandoDemo] = useState(false);
  const [errorRed, setErrorRed] = useState(null);
  const [pagina, setPagina] = useState(1);
  const [ultimaPagina, setUltimaPagina] = useState(1);

  const cargarHistorial = useCallback(
    async (page = 1) => {
      if (!estaAutenticado) {
        setItems(ITEMS_MUESTRA);
        setUsandoDemo(true);
        setErrorRed(null);
        setPagina(1);
        setUltimaPagina(1);
        return;
      }

      setCargando(true);
      setErrorRed(null);
      try {
        const res = await obtenerHistorial(page);
        const listaCruda = res?.historial || res?.items || res?.data || [];
        setUsandoDemo(false);
        setPagina(Number(res?.pagina_actual) || page);
        setUltimaPagina(Number(res?.ultima_pagina) || 1);

        if (page === 1) {
          setItems(agruparRegistros(listaCruda));
        } else {
          setItems((prev) => agruparRegistros([...prev, ...listaCruda]));
        }
      } catch (err) {
        setErrorRed(err?.message || 'No se pudo cargar el historial del servidor.');
        if (page === 1) {
          setItems(ITEMS_MUESTRA);
          setUsandoDemo(true);
        }
      } finally {
        setCargando(false);
      }
    },
    [estaAutenticado]
  );

  useEffect(() => {
    cargarHistorial(1);
  }, [cargarHistorial]);

  useFocusEffect(
    useCallback(() => {
      cargarHistorial(1);
    }, [cargarHistorial])
  );

  const itemsFiltrados = items.filter((item) => {
    const textoItem = (
      item.resumen_contenido ||
      item.contenido_original ||
      item.url_origen ||
      ''
    ).toLowerCase();
    const coincideBusqueda = textoItem.includes(busqueda.trim().toLowerCase());

    if (!coincideBusqueda) return false;
    if (filtroVeredicto === 'TODOS') return true;
    if (filtroVeredicto === 'GUARDADOS') return Boolean(item.guardado);

    const clave = normalizarVeredicto(item.veredicto?.tipo_veredicto).clave;
    if (filtroVeredicto === 'VERDADERO') return clave === 'VERDADERO';
    if (filtroVeredicto === 'FALSO') return clave === 'FALSO';
    if (filtroVeredicto === 'ENGANOSO') return clave === 'ENGANOSO' || clave === 'PARCIAL';
    return true;
  });

  const renderItem = ({ item }) => {
    const v = item.veredicto;
    const textoMostrar =
      item.resumen_contenido || item.contenido_original || item.url_origen || 'Contenido verificado';
    const fechaMostrar = item.fecha_evento || item.fecha_subida;
    const certeza = Math.round(Number(v?.nivel_confiabilidad) || 0);

    return (
      <TouchableOpacity
        style={styles.card}
        onPress={() =>
          navigation.navigate('Resultado', {
            idContenido: item.id_contenido,
            contenido: {
              ...item,
              contenido_original: item.contenido_original || item.resumen_contenido,
              fecha_subida: item.fecha_subida || item.fecha_evento,
            },
            veredicto: v,
            fuentes: item.veredicto?.fuentes || item.fuentes || [],
          })
        }
        activeOpacity={0.8}
        accessibilityRole="button"
        accessibilityLabel={`Ver detalles de verificación: ${textoMostrar}`}
      >
        <View style={styles.cardHeader}>
          <View style={styles.cardBadges}>
            <VerdictBadge tipo={v?.tipo_veredicto} compacto />
            {item.guardado ? (
              <View style={styles.savedPill}>
                <Text style={[styles.savedPillText, { fontSize: font(11) }]}>⭐ Guardada</Text>
              </View>
            ) : null}
          </View>
          <Text style={[styles.cardDate, { fontSize: font(12) }]}>
            {fechaMostrar
              ? new Date(fechaMostrar).toLocaleDateString('es-CO', {
                  day: 'numeric',
                  month: 'short',
                })
              : ''}
          </Text>
        </View>

        <Text
          style={[styles.cardText, { fontSize: font(15) }]}
          numberOfLines={3}
          ellipsizeMode="tail"
        >
          “{textoMostrar}”
        </Text>

        <View style={styles.cardFooter}>
          <Text style={[styles.cardCertainty, { fontSize: font(12) }]}>
            🎯 {certeza}% de certeza
          </Text>
          <Text style={[styles.cardArrow, { fontSize: font(13) }]}>Ver detalle ➔</Text>
        </View>
      </TouchableOpacity>
    );
  };

  return (
    <View style={styles.container}>
      {/* Barra de búsqueda */}
      <View style={styles.searchContainer}>
        <TextInput
          style={[styles.searchInput, { fontSize: font(15) }]}
          placeholder="🔍 Buscar en su historial..."
          placeholderTextColor={COLORS.tintaSuave}
          value={busqueda}
          onChangeText={setBusqueda}
        />
      </View>

      {/* Chips de filtro por veredicto */}
      <View style={styles.filterBarWrap}>
        <ScrollView
          horizontal
          showsHorizontalScrollIndicator={false}
          contentContainerStyle={styles.filterBar}
        >
          {[
            { key: 'TODOS', label: 'Todos' },
            { key: 'GUARDADOS', label: '⭐ Guardadas' },
            { key: 'VERDADERO', label: '✅ Verdadero' },
            { key: 'FALSO', label: '❌ Falso' },
            { key: 'ENGANOSO', label: '⚠️ Engañoso' },
          ].map((filtro) => {
            const activo = filtroVeredicto === filtro.key;
            return (
              <TouchableOpacity
                key={filtro.key}
                style={[styles.chip, activo && styles.chipActive]}
                onPress={() => setFiltroVeredicto(filtro.key)}
                activeOpacity={0.7}
              >
                <Text
                  style={[
                    styles.chipText,
                    { fontSize: font(13) },
                    activo && styles.chipTextActive,
                  ]}
                >
                  {filtro.label}
                </Text>
              </TouchableOpacity>
            );
          })}
        </ScrollView>
      </View>

      {/* Aviso de sesión o error de red */}
      {(!estaAutenticado || errorRed) && (
        <TouchableOpacity
          style={styles.noticeBanner}
          onPress={() => {
            if (!estaAutenticado) navigation.navigate('Mi cuenta');
            else cargarHistorial(1);
          }}
          activeOpacity={0.85}
        >
          <Text style={[styles.noticeText, { fontSize: font(13) }]}>
            {!estaAutenticado
              ? '👤 Inicie sesión en "Mi cuenta" para ver y sincronizar su historial real. Mostrando ejemplos:'
              : `⚠️ ${errorRed} Mostrando ejemplos locales (toque para reintentar).`}
          </Text>
        </TouchableOpacity>
      )}

      {/* Lista de contenidos */}
      <FlatList
        data={itemsFiltrados}
        keyExtractor={(item, idx) => String(item.id_contenido || item.id_historial || idx)}
        renderItem={renderItem}
        contentContainerStyle={styles.listContent}
        refreshControl={
          <RefreshControl refreshing={cargando} onRefresh={() => cargarHistorial(1)} />
        }
        ListFooterComponent={
          !usandoDemo && pagina < ultimaPagina ? (
            <TouchableOpacity
              style={styles.loadMoreBtn}
              onPress={() => cargarHistorial(pagina + 1)}
              disabled={cargando}
              activeOpacity={0.8}
            >
              <Text style={[styles.loadMoreText, { fontSize: font(14) }]}>
                {cargando ? 'Cargando…' : 'Cargar más verificaciones'}
              </Text>
            </TouchableOpacity>
          ) : null
        }
        ListEmptyComponent={
          !cargando ? (
            <View style={styles.emptyContainer}>
              <Text style={styles.emptyIcon}>🗂️</Text>
              <Text style={[styles.emptyTitle, { fontSize: font(17) }]}>
                {items.length > 0
                  ? 'Ninguna noticia coincide con este filtro'
                  : 'No hay noticias en su historial'}
              </Text>
              <Text style={[styles.emptySubtitle, { fontSize: font(14) }]}>
                {items.length > 0
                  ? 'Pruebe cambiando el texto de búsqueda o seleccionando "Todos".'
                  : 'Las noticias que verifique y guarde aparecerán aquí para consultarlas cuando las necesite.'}
              </Text>
              {items.length === 0 && (
                <TouchableOpacity
                  style={styles.newVerifyBtn}
                  onPress={() => navigation.navigate('Verificar')}
                  activeOpacity={0.8}
                >
                  <Text style={[styles.newVerifyBtnText, { fontSize: font(15) }]}>
                    🔍 Verificar una noticia ahora
                  </Text>
                </TouchableOpacity>
              )}
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
  searchContainer: {
    paddingHorizontal: 16,
    paddingTop: 14,
    paddingBottom: 8,
    backgroundColor: COLORS.blanco,
    borderBottomWidth: 1,
    borderBottomColor: COLORS.borde,
  },
  searchInput: {
    minHeight: 46,
    backgroundColor: COLORS.papel,
    borderRadius: 8,
    borderWidth: 2,
    borderColor: COLORS.borde,
    paddingHorizontal: 14,
    color: COLORS.tinta,
  },
  filterBarWrap: {
    backgroundColor: COLORS.blanco,
    borderBottomWidth: 1,
    borderBottomColor: COLORS.borde,
  },
  filterBar: {
    flexDirection: 'row',
    paddingHorizontal: 16,
    paddingVertical: 10,
    gap: 8,
  },
  chip: {
    paddingHorizontal: 12,
    paddingVertical: 6,
    borderRadius: 20,
    borderWidth: 1,
    borderColor: COLORS.borde,
    backgroundColor: COLORS.papel,
  },
  chipActive: {
    backgroundColor: COLORS.marca,
    borderColor: COLORS.marca,
  },
  chipText: {
    fontWeight: '700',
    color: COLORS.tintaSuave,
  },
  chipTextActive: {
    color: COLORS.blanco,
  },
  noticeBanner: {
    backgroundColor: COLORS.parcial.bg,
    borderBottomWidth: 1,
    borderBottomColor: COLORS.parcial.borde,
    paddingHorizontal: 16,
    paddingVertical: 10,
  },
  noticeText: {
    color: COLORS.parcial.color,
    fontWeight: '600',
    lineHeight: 18,
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
    alignItems: 'center',
    marginBottom: 8,
    gap: 8,
  },
  cardBadges: {
    flexDirection: 'row',
    alignItems: 'center',
    flexWrap: 'wrap',
    gap: 6,
    flex: 1,
  },
  savedPill: {
    backgroundColor: COLORS.parcial.bg,
    borderWidth: 1,
    borderColor: COLORS.parcial.borde,
    paddingHorizontal: 8,
    paddingVertical: 2,
    borderRadius: 12,
  },
  savedPillText: {
    color: COLORS.parcial.color,
    fontWeight: '700',
  },
  cardDate: {
    color: COLORS.tintaMuted,
    fontWeight: '600',
  },
  cardText: {
    fontWeight: '600',
    color: COLORS.tinta,
    lineHeight: 21,
  },
  cardFooter: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginTop: 12,
    paddingTop: 8,
    borderTopWidth: 1,
    borderTopColor: COLORS.borde,
  },
  cardCertainty: {
    color: COLORS.tintaSuave,
    fontWeight: '700',
  },
  cardArrow: {
    fontWeight: '700',
    color: COLORS.marca,
  },
  loadMoreBtn: {
    minHeight: 46,
    backgroundColor: COLORS.blanco,
    borderWidth: 2,
    borderColor: COLORS.bordeFuerte,
    borderRadius: 8,
    alignItems: 'center',
    justifyContent: 'center',
    marginTop: 4,
  },
  loadMoreText: {
    color: COLORS.tinta,
    fontWeight: '700',
  },
  emptyContainer: {
    padding: 30,
    alignItems: 'center',
    marginTop: 40,
  },
  emptyIcon: {
    fontSize: 50,
    marginBottom: 10,
  },
  emptyTitle: {
    fontWeight: '800',
    color: COLORS.tinta,
    textAlign: 'center',
    marginBottom: 6,
  },
  emptySubtitle: {
    color: COLORS.tintaSuave,
    textAlign: 'center',
    lineHeight: 20,
    marginBottom: 20,
  },
  newVerifyBtn: {
    minHeight: 48,
    backgroundColor: COLORS.marca,
    paddingHorizontal: 20,
    borderRadius: 8,
    alignItems: 'center',
    justifyContent: 'center',
  },
  newVerifyBtnText: {
    color: COLORS.blanco,
    fontWeight: '700',
  },
});
