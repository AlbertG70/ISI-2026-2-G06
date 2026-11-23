import React from 'react';
import { View, Text, StyleSheet } from 'react-native';
import { normalizarVeredicto } from '../theme/colors';
import { useTheme } from '../context/ThemeContext';

export default function VerdictBadge({ tipo, compacto = false }) {
  const { font } = useTheme();
  const v = normalizarVeredicto(tipo);

  return (
    <View
      style={[
        styles.badge,
        { backgroundColor: v.bg, borderColor: v.borde },
        compacto && styles.badgeCompacto,
      ]}
      accessibilityRole="text"
      accessibilityLabel={`Veredicto: ${v.etiqueta}`}
    >
      <Text style={[styles.icon, { fontSize: font(compacto ? 14 : 16) }]}>
        {v.icono}
      </Text>
      <Text
        style={[
          styles.text,
          { color: v.color, fontSize: font(compacto ? 12 : 14) },
        ]}
      >
        {v.etiqueta}
      </Text>
    </View>
  );
}

const styles = StyleSheet.create({
  badge: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    borderWidth: 2,
    borderRadius: 20,
    paddingHorizontal: 12,
    paddingVertical: 5,
    alignSelf: 'flex-start',
  },
  badgeCompacto: {
    paddingHorizontal: 8,
    paddingVertical: 3,
    borderRadius: 14,
  },
  icon: {
    lineHeight: 18,
  },
  text: {
    fontWeight: '700',
  },
});
