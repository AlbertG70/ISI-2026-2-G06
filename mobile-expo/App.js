import React from 'react';
import { View, StyleSheet, Text } from 'react-native';
import { SafeAreaProvider } from 'react-native-safe-area-context';
import { StatusBar } from 'expo-status-bar';
import { NavigationContainer } from '@react-navigation/native';
import { createBottomTabNavigator } from '@react-navigation/bottom-tabs';

import { ThemeProvider, useTheme } from './src/context/ThemeContext';
import { AuthProvider } from './src/context/AuthContext';
import { COLORS } from './src/theme/colors';
import Header from './src/components/Header';

import VerificarScreen from './src/screens/VerificarScreen';
import ResultadoScreen from './src/screens/ResultadoScreen';
import HistorialScreen from './src/screens/HistorialScreen';
import FuentesScreen from './src/screens/FuentesScreen';
import PerfilScreen from './src/screens/PerfilScreen';

const Tab = createBottomTabNavigator();

// Stack para el flujo de Verificación (Entrada -> Resultado)
function VerificarStack({ navigation, route }) {
  const [rutaInterna, setRutaInterna] = React.useState('VerificarEntrada');
  const [paramsResultado, setParamsResultado] = React.useState(null);

  // Navegación interna simple sin depender de @react-navigation/stack
  const customNav = {
    navigate: (pantalla, params) => {
      if (pantalla === 'Resultado') {
        setParamsResultado(params);
        setRutaInterna('Resultado');
      } else if (pantalla === 'Mi cuenta') {
        navigation.navigate('Mi cuenta');
      } else {
        setRutaInterna('VerificarEntrada');
      }
    },
    goBack: () => {
      setRutaInterna('VerificarEntrada');
    },
  };

  if (rutaInterna === 'Resultado') {
    return (
      <ResultadoScreen
        navigation={customNav}
        route={{ params: paramsResultado }}
      />
    );
  }

  return <VerificarScreen navigation={customNav} />;
}

// Stack para Historial -> Detalle de Resultado
function HistorialStack({ navigation }) {
  const [rutaInterna, setRutaInterna] = React.useState('HistorialLista');
  const [paramsResultado, setParamsResultado] = React.useState(null);

  const customNav = {
    navigate: (pantalla, params) => {
      if (pantalla === 'Resultado') {
        setParamsResultado(params);
        setRutaInterna('Resultado');
      } else if (pantalla === 'Verificar') {
        navigation.navigate('Verificar');
      } else if (pantalla === 'Mi cuenta') {
        navigation.navigate('Mi cuenta');
      }
    },
    goBack: () => {
      setRutaInterna('HistorialLista');
    },
  };

  if (rutaInterna === 'Resultado') {
    return (
      <ResultadoScreen
        navigation={customNav}
        route={{ params: paramsResultado }}
      />
    );
  }

  return <HistorialScreen navigation={customNav} />;
}

function MainTabs() {
  const { font } = useTheme();

  return (
    <View style={styles.container}>
      <Header />
      <Tab.Navigator
        screenOptions={{
          headerShown: false,
          tabBarActiveTintColor: COLORS.tinta,
          tabBarInactiveTintColor: COLORS.tintaSuave,
          tabBarStyle: styles.tabBar,
          tabBarLabelStyle: {
            fontSize: font(12),
            fontWeight: '700',
            marginBottom: 4,
          },
          tabBarItemStyle: {
            paddingVertical: 4,
          },
        }}
      >
        <Tab.Screen
          name="Verificar"
          component={VerificarStack}
          options={{
            tabBarLabel: 'Verificar',
            tabBarIcon: ({ focused }) => (
              <Text style={[styles.tabIcon, focused && styles.tabIconActive]}>
                🔍
              </Text>
            ),
          }}
        />

        <Tab.Screen
          name="Historial"
          component={HistorialStack}
          options={{
            tabBarLabel: 'Historial',
            tabBarIcon: ({ focused }) => (
              <Text style={[styles.tabIcon, focused && styles.tabIconActive]}>
                🗂️
              </Text>
            ),
          }}
        />

        <Tab.Screen
          name="Fuentes"
          component={FuentesScreen}
          options={{
            tabBarLabel: 'Fuentes',
            tabBarIcon: ({ focused }) => (
              <Text style={[styles.tabIcon, focused && styles.tabIconActive]}>
                📰
              </Text>
            ),
          }}
        />

        <Tab.Screen
          name="Mi cuenta"
          component={PerfilScreen}
          options={{
            tabBarLabel: 'Mi cuenta',
            tabBarIcon: ({ focused }) => (
              <Text style={[styles.tabIcon, focused && styles.tabIconActive]}>
                👤
              </Text>
            ),
          }}
        />
      </Tab.Navigator>
    </View>
  );
}

export default function App() {
  return (
    <SafeAreaProvider>
      <ThemeProvider>
        <AuthProvider>
          <StatusBar style="dark" backgroundColor={COLORS.blanco} />
          <NavigationContainer>
            <MainTabs />
          </NavigationContainer>
        </AuthProvider>
      </ThemeProvider>
    </SafeAreaProvider>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: COLORS.papel,
  },
  tabBar: {
    backgroundColor: COLORS.blanco,
    borderTopWidth: 2,
    borderTopColor: COLORS.borde,
    minHeight: 64,
    paddingTop: 6,
    paddingBottom: 8,
  },
  tabIcon: {
    fontSize: 22,
    opacity: 0.8,
  },
  tabIconActive: {
    opacity: 1,
    transform: [{ scale: 1.15 }],
  },
});
