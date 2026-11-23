import React from 'react';
import { View, Text, StyleSheet } from 'react-native';
import { useTheme } from '../context/ThemeContext';
import { COLORS } from '../theme/colors';

export default function CircularGauge({ porcentaje = 0, color = COLORS.marca, size = 74 }) {
  const { font } = useTheme();
  const valor = Math.max(0, Math.min(100, Math.round(Number(porcentaje) || 0)));

  return (
    <View style={styles.wrapper}>
      <View
        style={[
          styles.container,
          {
            width: size,
            height: size,
            borderRadius: size / 2,
            borderColor: color,
          },
        ]}
      >
        <Text style={[styles.number, { color, fontSize: font(18) }]}>
          {valor}%
        </Text>
      </View>
      <Text style={[styles.label, { fontSize: font(11) }]}>CERTEZA</Text>
    </View>
  );
}

const styles = StyleSheet.create({
  wrapper: {
    alignItems: 'center',
    justifyContent: 'center',
  },
  container: {
    borderWidth: 4,
    backgroundColor: COLORS.blanco,
    alignItems: 'center',
    justifyContent: 'center',
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 1 },
    shadowOpacity: 0.1,
    shadowRadius: 2,
    elevation: 2,
  },
  number: {
    fontWeight: '800',
  },
  label: {
    marginTop: 4,
    fontWeight: '800',
    color: COLORS.tintaSuave,
    letterSpacing: 0.8,
  },
});
