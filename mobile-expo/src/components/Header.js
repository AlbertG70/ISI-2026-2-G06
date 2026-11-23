import React from 'react';
import { View, Text, TouchableOpacity, StyleSheet } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { COLORS } from '../theme/colors';
import { useTheme } from '../context/ThemeContext';

export default function Header({ titulo, subtitulo }) {
  const insets = useSafeAreaInsets();
  const { alternarTamanoTexto, font, esGrande } = useTheme();

  return (
    <View style={[styles.container, { paddingTop: Math.max(insets.top, 12) }]}>
      <View style={styles.content}>
        {/* Logo DAGAZ: Runa ᛞ */}
        <View style={styles.brandRow}>
          <View style={styles.logoBadge}>
            <Text style={styles.logoText}>ᛞ</Text>
          </View>
          <View>
            <Text style={[styles.title, { fontSize: font(20) }]}>
              {titulo || 'DAGAZ'}
            </Text>
            <Text style={[styles.subtitle, { fontSize: font(12) }]}>
              {subtitulo || 'Fact-Checking Ciudadano'}
            </Text>
          </View>
        </View>

        {/* Switcher Accesible A / A+ (+40 años) */}
        <TouchableOpacity
          onPress={alternarTamanoTexto}
          style={[styles.accessibilityBtn, esGrande && styles.accessibilityBtnActive]}
          accessibilityRole="button"
          accessibilityLabel={`Cambiar tamaño de texto. Actualmente ${esGrande ? 'Grande' : 'Normal'}`}
          activeOpacity={0.7}
        >
          <Text
            style={[
              styles.accessibilityText,
              { fontSize: font(14) },
              esGrande && styles.accessibilityTextActive,
            ]}
          >
            {esGrande ? 'A+' : 'A'}
          </Text>
        </TouchableOpacity>
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    backgroundColor: COLORS.blanco,
    borderBottomWidth: 2,
    borderBottomColor: COLORS.borde,
    paddingHorizontal: 16,
    paddingBottom: 12,
  },
  content: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
  },
  brandRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
  },
  logoBadge: {
    width: 38,
    height: 38,
    borderRadius: 8,
    backgroundColor: COLORS.marca,
    alignItems: 'center',
    justifyContent: 'center',
  },
  logoText: {
    color: COLORS.blanco,
    fontSize: 22,
    fontWeight: 'bold',
  },
  title: {
    fontWeight: '800',
    color: COLORS.tinta,
    letterSpacing: 0.5,
  },
  subtitle: {
    color: COLORS.tintaSuave,
    fontWeight: '500',
  },
  accessibilityBtn: {
    minWidth: 48,
    minHeight: 48,
    borderRadius: 24,
    borderWidth: 2,
    borderColor: COLORS.borde,
    backgroundColor: COLORS.papel,
    alignItems: 'center',
    justifyContent: 'center',
    paddingHorizontal: 10,
  },
  accessibilityBtnActive: {
    backgroundColor: COLORS.marca,
    borderColor: COLORS.marca,
  },
  accessibilityText: {
    fontWeight: '800',
    color: COLORS.tinta,
  },
  accessibilityTextActive: {
    color: COLORS.blanco,
  },
});
