import React, { createContext, useContext, useState, useEffect } from 'react';
import AsyncStorage from '@react-native-async-storage/async-storage';

const ThemeContext = createContext();

export function ThemeProvider({ children }) {
  // 'normal' o 'grande' (1.25x scale para adultos +40)
  const [tamanoTexto, setTamanoTexto] = useState('normal');

  useEffect(() => {
    AsyncStorage.getItem('dagaz_tamano_texto').then((val) => {
      if (val === 'grande' || val === 'normal') setTamanoTexto(val);
    }).catch(() => {});
  }, []);

  const alternarTamanoTexto = async () => {
    const nuevo = tamanoTexto === 'normal' ? 'grande' : 'normal';
    setTamanoTexto(nuevo);
    try {
      await AsyncStorage.setItem('dagaz_tamano_texto', nuevo);
    } catch {}
  };

  const factor = tamanoTexto === 'grande' ? 1.25 : 1.0;

  // Utilidad para escalar tamaños de fuente dinámicamente
  const font = (tamanoBase) => Math.round(tamanoBase * factor);

  return (
    <ThemeContext.Provider value={{ tamanoTexto, alternarTamanoTexto, font, esGrande: tamanoTexto === 'grande' }}>
      {children}
    </ThemeContext.Provider>
  );
}

export const useTheme = () => useContext(ThemeContext);
