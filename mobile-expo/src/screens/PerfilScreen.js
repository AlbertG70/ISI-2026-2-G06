import React, { useState, useEffect } from 'react';
import {
  View,
  Text,
  TextInput,
  TouchableOpacity,
  ScrollView,
  StyleSheet,
  Alert,
  Share,
  Modal,
  ActivityIndicator,
} from 'react-native';
import { COLORS } from '../theme/colors';
import { useTheme } from '../context/ThemeContext';
import { useAuth } from '../context/AuthContext';
import {
  exportarDatosPersonalesApi,
  eliminarCuentaApi,
  probarConexionApi,
} from '../services/api';

const ROLES_ETIQUETA = {
  adulto: 'Ciudadano Verificador',
  admin: 'Administrador',
  editor: 'Editor / Revisor',
  lector: 'Lector',
};

export default function PerfilScreen() {
  const { font, alternarTamanoTexto, esGrande } = useTheme();
  const {
    usuario,
    estaAutenticado,
    login,
    registro,
    logout,
    limpiarSesionLocal,
    urlServidor,
    actualizarUrlServidor,
  } = useAuth();

  const [modo, setModo] = useState('login'); // 'login' | 'registro'
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [passwordConfirm, setPasswordConfirm] = useState('');
  const [aceptaLey1581, setAceptaLey1581] = useState(false);
  const [cargandoAuth, setCargandoAuth] = useState(false);

  // Configuración del servidor backend
  const [inputServidor, setInputServidor] = useState(urlServidor);
  const [guardadoServidor, setGuardadoServidor] = useState(false);
  const [probandoConexion, setProbandoConexion] = useState(false);
  const [estadoConexion, setEstadoConexion] = useState(null);

  // Derechos ARCO (Ley 1581)
  const [exportandoDatos, setExportandoDatos] = useState(false);
  const [modalEliminarVisible, setModalEliminarVisible] = useState(false);
  const [passwordEliminar, setPasswordEliminar] = useState('');
  const [eliminandoCuenta, setEliminandoCuenta] = useState(false);
  const [errorEliminar, setErrorEliminar] = useState('');

  useEffect(() => {
    setInputServidor(urlServidor);
  }, [urlServidor]);

  const handleLogin = async () => {
    const emailLimpio = email.trim();
    if (!emailLimpio || !password) {
      Alert.alert('Campos incompletos', 'Ingrese su correo electrónico y contraseña.');
      return;
    }
    setCargandoAuth(true);
    try {
      await login(emailLimpio, password);
      setPassword('');
      Alert.alert('¡Bienvenido!', 'Ha iniciado sesión en DAGAZ.');
    } catch (err) {
      Alert.alert('Error de acceso', err.message || 'Credenciales incorrectas o servidor no disponible.');
    } finally {
      setCargandoAuth(false);
    }
  };

  const handleRegistro = async () => {
    const emailLimpio = email.trim();
    if (!emailLimpio || !emailLimpio.includes('@')) {
      Alert.alert('Correo inválido', 'Ingrese un correo electrónico válido.');
      return;
    }
    if (password.length < 8) {
      Alert.alert('Contraseña muy corta', 'La contraseña debe tener al menos 8 caracteres.');
      return;
    }
    if (password !== passwordConfirm) {
      Alert.alert('Contraseñas no coinciden', 'Verifique que ambas contraseñas sean idénticas.');
      return;
    }
    if (!aceptaLey1581) {
      Alert.alert(
        'Consentimiento Requerido',
        'Debe autorizar el tratamiento de datos personales de acuerdo con la Ley Estatutaria 1581 de 2012.'
      );
      return;
    }

    setCargandoAuth(true);
    try {
      await registro(emailLimpio, password, passwordConfirm);
      setPassword('');
      setPasswordConfirm('');
      Alert.alert('Cuenta creada', '¡Bienvenido a DAGAZ! Ya puede guardar verificaciones y aportar notas.');
    } catch (err) {
      Alert.alert('Error al registrarse', err.message || 'No se pudo completar el registro.');
    } finally {
      setCargandoAuth(false);
    }
  };

  const handleGuardarServidor = async () => {
    if (!inputServidor.trim()) return;
    const guardada = await actualizarUrlServidor(inputServidor.trim());
    setInputServidor(guardada);
    setGuardadoServidor(true);
    setEstadoConexion(null);
    Alert.alert('Configuración guardada', `El servidor backend se actualizó a:\n${guardada}`);
    setTimeout(() => setGuardadoServidor(false), 3000);
  };

  const handleProbarConexion = async () => {
    if (!inputServidor.trim()) return;
    setProbandoConexion(true);
    setEstadoConexion(null);
    try {
      const guardada = await actualizarUrlServidor(inputServidor.trim());
      setInputServidor(guardada);
      const salud = await probarConexionApi(guardada);
      setEstadoConexion({
        ok: true,
        texto: `Conectado a ${salud?.sistema || 'DAGAZ'} v${salud?.version || ''} (${salud?.estado || 'operativo'})`,
      });
    } catch (err) {
      setEstadoConexion({
        ok: false,
        texto: err?.message || 'No fue posible alcanzar el servidor.',
      });
    } finally {
      setProbandoConexion(false);
    }
  };

  const handleDescargarDatos = async () => {
    setExportandoDatos(true);
    try {
      const datos = await exportarDatosPersonalesApi();
      const jsonTexto = JSON.stringify(datos, null, 2);
      const totalContenidos = datos?.actividad?.total_contenidos ?? 0;
      const totalNotas = datos?.actividad?.total_notas ?? 0;

      Alert.alert(
        'Garantía Ley 1581 (Hábeas Data)',
        `Expediente generado exitosamente (${totalContenidos} verificaciones y ${totalNotas} notas). ¿Desea compartir o guardar el archivo JSON?`,
        [
          { text: 'Cerrar', style: 'cancel' },
          {
            text: 'Compartir / Guardar JSON',
            onPress: () => {
              Share.share({
                title: 'Mis Datos Personales DAGAZ (Ley 1581)',
                message: jsonTexto,
              }).catch(() => {});
            },
          },
        ]
      );
    } catch (err) {
      Alert.alert('Error al exportar', err?.message || 'No se pudo descargar su expediente.');
    } finally {
      setExportandoDatos(false);
    }
  };

  const abrirModalOlvido = () => {
    setPasswordEliminar('');
    setErrorEliminar('');
    setModalEliminarVisible(true);
  };

  const confirmarDerechoAlOlvido = async () => {
    if (!passwordEliminar) {
      setErrorEliminar('Ingrese su contraseña actual para confirmar.');
      return;
    }
    setEliminandoCuenta(true);
    setErrorEliminar('');
    try {
      const res = await eliminarCuentaApi(passwordEliminar);
      await limpiarSesionLocal();
      setModalEliminarVisible(false);
      setPasswordEliminar('');
      Alert.alert(
        'Cuenta Suprimida',
        res?.mensaje || 'Sus datos personales fueron suprimidos de forma definitiva según la Ley 1581 de 2012.'
      );
    } catch (err) {
      setErrorEliminar(err?.message || 'Contraseña incorrecta o error del servidor.');
    } finally {
      setEliminandoCuenta(false);
    }
  };

  const inicialAvatar = (usuario?.email || 'U')[0].toUpperCase();
  const nombreVisible = usuario?.email ? String(usuario.email).split('@')[0] : 'Usuario';
  const rolVisible = ROLES_ETIQUETA[usuario?.rol] || usuario?.rol || 'Ciudadano Verificador';

  return (
    <ScrollView style={styles.container} contentContainerStyle={styles.content}>
      {/* Estado de Sesión */}
      {estaAutenticado ? (
        <View style={styles.card}>
          <View style={styles.userHeader}>
            <View style={styles.avatar}>
              <Text style={styles.avatarText}>{inicialAvatar}</Text>
            </View>
            <View style={styles.userInfo}>
              <Text style={[styles.userName, { fontSize: font(18) }]}>
                {nombreVisible}
              </Text>
              <Text style={[styles.userEmail, { fontSize: font(14) }]}>
                {usuario?.email}
              </Text>
              <View style={styles.roleBadge}>
                <Text style={[styles.roleText, { fontSize: font(12) }]}>
                  🛡️ Rol: {rolVisible}
                </Text>
              </View>
              {usuario?.fecha_creacion ? (
                <Text style={[styles.joinedText, { fontSize: font(11) }]}>
                  Miembro desde{' '}
                  {new Date(usuario.fecha_creacion).toLocaleDateString('es-CO', {
                    day: 'numeric',
                    month: 'short',
                    year: 'numeric',
                  })}
                </Text>
              ) : null}
            </View>
          </View>

          <TouchableOpacity
            style={styles.logoutBtn}
            onPress={logout}
            activeOpacity={0.8}
          >
            <Text style={[styles.logoutBtnText, { fontSize: font(15) }]}>
              Cerrar Sesión
            </Text>
          </TouchableOpacity>

          {/* Garantías Ley 1581 de Hábeas Data */}
          <View style={styles.arcoSection}>
            <Text style={[styles.arcoTitle, { fontSize: font(16) }]}>
              ⚖️ Derechos ARCO y Ley 1581 de 2012
            </Text>
            <Text style={[styles.arcoDesc, { fontSize: font(13) }]}>
              Garantía colombiana de protección y control de sus datos personales:
            </Text>
            <View style={styles.arcoButtons}>
              <TouchableOpacity
                style={styles.arcoBtn}
                onPress={handleDescargarDatos}
                disabled={exportandoDatos}
                activeOpacity={0.7}
              >
                <Text style={[styles.arcoBtnText, { fontSize: font(13) }]}>
                  {exportandoDatos
                    ? 'Preparando expediente JSON...'
                    : '📥 Exportar mis datos personales'}
                </Text>
              </TouchableOpacity>

              <TouchableOpacity
                style={styles.arcoBtnDanger}
                onPress={abrirModalOlvido}
                activeOpacity={0.7}
              >
                <Text style={[styles.arcoBtnDangerText, { fontSize: font(13) }]}>
                  🗑️ Ejercer derecho al olvido (Eliminar cuenta)
                </Text>
              </TouchableOpacity>
            </View>
          </View>
        </View>
      ) : (
        <View style={styles.card}>
          {/* Selector Login / Registro */}
          <View style={styles.authTabs}>
            <TouchableOpacity
              style={[styles.authTab, modo === 'login' && styles.authTabActive]}
              onPress={() => setModo('login')}
            >
              <Text style={[styles.authTabText, modo === 'login' && styles.authTabTextActive]}>
                Iniciar Sesión
              </Text>
            </TouchableOpacity>

            <TouchableOpacity
              style={[styles.authTab, modo === 'registro' && styles.authTabActive]}
              onPress={() => setModo('registro')}
            >
              <Text style={[styles.authTabText, modo === 'registro' && styles.authTabTextActive]}>
                Crear Cuenta
              </Text>
            </TouchableOpacity>
          </View>

          {modo === 'login' ? (
            <View style={styles.form}>
              <Text style={[styles.fieldLabel, { fontSize: font(15) }]}>Correo electrónico:</Text>
              <TextInput
                style={[styles.input, { fontSize: font(15) }]}
                placeholder="ejemplo@correo.com"
                placeholderTextColor={COLORS.tintaSuave}
                keyboardType="email-address"
                autoCapitalize="none"
                autoCorrect={false}
                value={email}
                onChangeText={setEmail}
              />

              <Text style={[styles.fieldLabel, { fontSize: font(15), marginTop: 12 }]}>
                Contraseña:
              </Text>
              <TextInput
                style={[styles.input, { fontSize: font(15) }]}
                placeholder="••••••••"
                placeholderTextColor={COLORS.tintaSuave}
                secureTextEntry
                value={password}
                onChangeText={setPassword}
              />

              <TouchableOpacity
                style={styles.authSubmitBtn}
                onPress={handleLogin}
                disabled={cargandoAuth}
                activeOpacity={0.8}
              >
                <Text style={[styles.authSubmitText, { fontSize: font(16) }]}>
                  {cargandoAuth ? 'Ingresando...' : 'Iniciar Sesión'}
                </Text>
              </TouchableOpacity>
            </View>
          ) : (
            <View style={styles.form}>
              <Text style={[styles.fieldLabel, { fontSize: font(15) }]}>
                Correo electrónico:
              </Text>
              <TextInput
                style={[styles.input, { fontSize: font(15) }]}
                placeholder="ejemplo@correo.com"
                placeholderTextColor={COLORS.tintaSuave}
                keyboardType="email-address"
                autoCapitalize="none"
                autoCorrect={false}
                value={email}
                onChangeText={setEmail}
              />

              <Text style={[styles.fieldLabel, { fontSize: font(15), marginTop: 10 }]}>
                Contraseña:
              </Text>
              <TextInput
                style={[styles.input, { fontSize: font(15) }]}
                placeholder="Mínimo 8 caracteres"
                placeholderTextColor={COLORS.tintaSuave}
                secureTextEntry
                value={password}
                onChangeText={setPassword}
              />

              <Text style={[styles.fieldLabel, { fontSize: font(15), marginTop: 10 }]}>
                Confirmar contraseña:
              </Text>
              <TextInput
                style={[styles.input, { fontSize: font(15) }]}
                placeholder="Repita su contraseña"
                placeholderTextColor={COLORS.tintaSuave}
                secureTextEntry
                value={passwordConfirm}
                onChangeText={setPasswordConfirm}
              />

              {/* Consentimiento Ley 1581 */}
              <TouchableOpacity
                style={styles.checkboxRow}
                onPress={() => setAceptaLey1581(!aceptaLey1581)}
                activeOpacity={0.8}
              >
                <View style={[styles.checkbox, aceptaLey1581 && styles.checkboxActive]}>
                  {aceptaLey1581 && <Text style={styles.checkMark}>✓</Text>}
                </View>
                <Text style={[styles.checkboxLabel, { fontSize: font(13) }]}>
                  Autorizo el tratamiento de mis datos personales de acuerdo con la Ley Estatutaria 1581 de 2012 de Colombia y la Política de Privacidad de DAGAZ.
                </Text>
              </TouchableOpacity>

              <TouchableOpacity
                style={styles.authSubmitBtn}
                onPress={handleRegistro}
                disabled={cargandoAuth}
                activeOpacity={0.8}
              >
                <Text style={[styles.authSubmitText, { fontSize: font(16) }]}>
                  {cargandoAuth ? 'Registrando...' : 'Crear Cuenta Segura'}
                </Text>
              </TouchableOpacity>
            </View>
          )}
        </View>
      )}

      {/* Ajustes de Accesibilidad */}
      <View style={styles.card}>
        <Text style={[styles.settingsTitle, { fontSize: font(17) }]}>
          👁️ Accesibilidad (+40 años)
        </Text>
        <Text style={[styles.settingsDesc, { fontSize: font(13) }]}>
          Optimizado con tipografía fluida y botones de gran tamaño según normas WCAG 2.1 AA.
        </Text>

        <View style={styles.accessibilityRow}>
          <Text style={[styles.accessibilityLabel, { fontSize: font(15) }]}>
            Tamaño de texto: {esGrande ? 'Grande (+30%)' : 'Estándar'}
          </Text>
          <TouchableOpacity
            style={[styles.toggleBtn, esGrande && styles.toggleBtnActive]}
            onPress={alternarTamanoTexto}
            activeOpacity={0.8}
          >
            <Text style={[styles.toggleBtnText, esGrande && styles.toggleBtnTextActive]}>
              {esGrande ? 'A+' : 'A'}
            </Text>
          </TouchableOpacity>
        </View>
      </View>

      {/* Configuración de Conexión / Servidor Backend */}
      <View style={styles.card}>
        <Text style={[styles.settingsTitle, { fontSize: font(17) }]}>
          ⚙️ Conexión del Backend (API REST)
        </Text>
        <Text style={[styles.settingsDesc, { fontSize: font(13) }]}>
          Escriba la IP local de su computadora en la misma red Wi-Fi (ej. http://192.168.1.32:8000) o su URL pública:
        </Text>

        <TextInput
          style={[styles.input, { fontSize: font(14), marginTop: 8 }]}
          placeholder="http://192.168.1.32:8000"
          placeholderTextColor={COLORS.tintaSuave}
          value={inputServidor}
          onChangeText={setInputServidor}
          autoCapitalize="none"
          autoCorrect={false}
        />

        <View style={styles.serverButtonsRow}>
          <TouchableOpacity
            style={[styles.saveServerBtn, styles.serverBtnFlex]}
            onPress={handleGuardarServidor}
            activeOpacity={0.8}
          >
            <Text style={[styles.saveServerBtnText, { fontSize: font(14) }]}>
              {guardadoServidor ? '✓ Guardada' : 'Guardar URL'}
            </Text>
          </TouchableOpacity>

          <TouchableOpacity
            style={[styles.testServerBtn, styles.serverBtnFlex]}
            onPress={handleProbarConexion}
            disabled={probandoConexion}
            activeOpacity={0.8}
          >
            {probandoConexion ? (
              <ActivityIndicator size="small" color={COLORS.blanco} />
            ) : (
              <Text style={[styles.testServerBtnText, { fontSize: font(14) }]}>
                Probar Conexión
              </Text>
            )}
          </TouchableOpacity>
        </View>

        {estadoConexion && (
          <View
            style={[
              styles.connectionStatusBox,
              estadoConexion.ok ? styles.connectionOkBox : styles.connectionErrBox,
            ]}
          >
            <Text
              style={[
                styles.connectionStatusText,
                { fontSize: font(13), color: estadoConexion.ok ? COLORS.verdadero.color : COLORS.falso.color },
              ]}
            >
              {estadoConexion.ok ? '✅ ' : '⚠️ '}
              {estadoConexion.texto}
            </Text>
          </View>
        )}
      </View>

      {/* Modal de Confirmación Derecho al Olvido (Ley 1581) */}
      <Modal
        visible={modalEliminarVisible}
        transparent
        animationType="fade"
        onRequestClose={() => setModalEliminarVisible(false)}
      >
        <View style={styles.modalBackdrop}>
          <View style={styles.modalCard}>
            <Text style={[styles.modalTitle, { fontSize: font(18) }]}>
              🗑️ Ejercer Derecho al Olvido
            </Text>
            <Text style={[styles.modalDesc, { fontSize: font(14) }]}>
              Esta acción eliminará su cuenta, historial y notas de manera definitiva e irreversible conforme a la Ley 1581 de 2012. Escriba su contraseña actual para confirmar:
            </Text>

            <TextInput
              style={[styles.input, { fontSize: font(15) }]}
              placeholder="Contraseña actual"
              placeholderTextColor={COLORS.tintaSuave}
              secureTextEntry
              value={passwordEliminar}
              onChangeText={setPasswordEliminar}
            />

            {errorEliminar ? (
              <Text style={[styles.modalErrorText, { fontSize: font(13) }]}>
                ⚠️ {errorEliminar}
              </Text>
            ) : null}

            <View style={styles.modalActions}>
              <TouchableOpacity
                style={styles.modalCancelBtn}
                onPress={() => setModalEliminarVisible(false)}
                disabled={eliminandoCuenta}
              >
                <Text style={[styles.modalCancelText, { fontSize: font(14) }]}>
                  Cancelar
                </Text>
              </TouchableOpacity>

              <TouchableOpacity
                style={styles.modalDeleteBtn}
                onPress={confirmarDerechoAlOlvido}
                disabled={eliminandoCuenta}
              >
                <Text style={[styles.modalDeleteText, { fontSize: font(14) }]}>
                  {eliminandoCuenta ? 'Eliminando...' : 'Eliminar Cuenta'}
                </Text>
              </TouchableOpacity>
            </View>
          </View>
        </View>
      </Modal>
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
  card: {
    backgroundColor: COLORS.blanco,
    borderRadius: 12,
    borderWidth: 2,
    borderColor: COLORS.borde,
    padding: 16,
  },
  userHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 14,
    marginBottom: 16,
  },
  avatar: {
    width: 54,
    height: 54,
    borderRadius: 27,
    backgroundColor: COLORS.marca,
    alignItems: 'center',
    justifyContent: 'center',
  },
  avatarText: {
    color: COLORS.blanco,
    fontSize: 24,
    fontWeight: '800',
  },
  userInfo: {
    flex: 1,
  },
  userName: {
    fontWeight: '800',
    color: COLORS.tinta,
  },
  userEmail: {
    color: COLORS.tintaSuave,
    marginTop: 2,
  },
  roleBadge: {
    backgroundColor: COLORS.neutroBg,
    paddingHorizontal: 8,
    paddingVertical: 3,
    borderRadius: 6,
    marginTop: 6,
    alignSelf: 'flex-start',
  },
  roleText: {
    color: COLORS.tinta,
    fontWeight: '700',
  },
  joinedText: {
    color: COLORS.tintaMuted,
    marginTop: 4,
  },
  logoutBtn: {
    minHeight: 46,
    borderWidth: 2,
    borderColor: COLORS.falso.color,
    borderRadius: 8,
    alignItems: 'center',
    justifyContent: 'center',
  },
  logoutBtnText: {
    color: COLORS.falso.color,
    fontWeight: '700',
  },
  arcoSection: {
    marginTop: 18,
    paddingTop: 14,
    borderTopWidth: 1,
    borderTopColor: COLORS.borde,
  },
  arcoTitle: {
    fontWeight: '800',
    color: COLORS.tinta,
  },
  arcoDesc: {
    color: COLORS.tintaSuave,
    marginTop: 4,
    marginBottom: 10,
    lineHeight: 18,
  },
  arcoButtons: {
    gap: 8,
  },
  arcoBtn: {
    minHeight: 44,
    borderWidth: 1.5,
    borderColor: COLORS.borde,
    borderRadius: 8,
    backgroundColor: COLORS.papel,
    alignItems: 'center',
    justifyContent: 'center',
    paddingHorizontal: 10,
  },
  arcoBtnText: {
    color: COLORS.tinta,
    fontWeight: '600',
  },
  arcoBtnDanger: {
    minHeight: 44,
    borderWidth: 1.5,
    borderColor: COLORS.falso.borde,
    borderRadius: 8,
    backgroundColor: COLORS.falso.bg,
    alignItems: 'center',
    justifyContent: 'center',
    paddingHorizontal: 10,
  },
  arcoBtnDangerText: {
    color: COLORS.falso.color,
    fontWeight: '700',
  },
  authTabs: {
    flexDirection: 'row',
    marginBottom: 16,
    borderBottomWidth: 1,
    borderBottomColor: COLORS.borde,
  },
  authTab: {
    flex: 1,
    minHeight: 44,
    alignItems: 'center',
    justifyContent: 'center',
    borderBottomWidth: 3,
    borderBottomColor: 'transparent',
  },
  authTabActive: {
    borderBottomColor: COLORS.marca,
  },
  authTabText: {
    fontWeight: '600',
    color: COLORS.tintaSuave,
  },
  authTabTextActive: {
    fontWeight: '800',
    color: COLORS.marca,
  },
  form: {
    gap: 4,
  },
  fieldLabel: {
    fontWeight: '700',
    color: COLORS.tinta,
  },
  input: {
    minHeight: 48,
    borderWidth: 2,
    borderColor: COLORS.borde,
    borderRadius: 8,
    paddingHorizontal: 12,
    backgroundColor: COLORS.papel,
    color: COLORS.tinta,
  },
  checkboxRow: {
    flexDirection: 'row',
    alignItems: 'flex-start',
    gap: 10,
    marginTop: 12,
    marginBottom: 10,
  },
  checkbox: {
    width: 24,
    height: 24,
    borderWidth: 2,
    borderColor: COLORS.bordeFuerte,
    borderRadius: 6,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: COLORS.blanco,
  },
  checkboxActive: {
    backgroundColor: COLORS.marca,
    borderColor: COLORS.marca,
  },
  checkMark: {
    color: COLORS.blanco,
    fontWeight: 'bold',
  },
  checkboxLabel: {
    flex: 1,
    color: COLORS.tinta,
    lineHeight: 18,
  },
  authSubmitBtn: {
    minHeight: 50,
    backgroundColor: COLORS.marca,
    borderRadius: 8,
    alignItems: 'center',
    justifyContent: 'center',
    marginTop: 10,
  },
  authSubmitText: {
    color: COLORS.blanco,
    fontWeight: '800',
  },
  settingsTitle: {
    fontWeight: '800',
    color: COLORS.tinta,
  },
  settingsDesc: {
    color: COLORS.tintaSuave,
    marginTop: 4,
    lineHeight: 18,
  },
  accessibilityRow: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    marginTop: 12,
  },
  accessibilityLabel: {
    fontWeight: '600',
    color: COLORS.tinta,
  },
  toggleBtn: {
    width: 44,
    height: 44,
    borderRadius: 22,
    borderWidth: 2,
    borderColor: COLORS.borde,
    backgroundColor: COLORS.papel,
    alignItems: 'center',
    justifyContent: 'center',
  },
  toggleBtnActive: {
    backgroundColor: COLORS.marca,
    borderColor: COLORS.marca,
  },
  toggleBtnText: {
    fontWeight: '800',
    color: COLORS.tinta,
  },
  toggleBtnTextActive: {
    color: COLORS.blanco,
  },
  serverButtonsRow: {
    flexDirection: 'row',
    gap: 10,
    marginTop: 10,
  },
  serverBtnFlex: {
    flex: 1,
  },
  saveServerBtn: {
    minHeight: 44,
    backgroundColor: COLORS.neutroBg,
    borderWidth: 1.5,
    borderColor: COLORS.borde,
    borderRadius: 8,
    alignItems: 'center',
    justifyContent: 'center',
    paddingHorizontal: 8,
  },
  saveServerBtnText: {
    fontWeight: '700',
    color: COLORS.tinta,
  },
  testServerBtn: {
    minHeight: 44,
    backgroundColor: COLORS.marca,
    borderRadius: 8,
    alignItems: 'center',
    justifyContent: 'center',
    paddingHorizontal: 8,
  },
  testServerBtnText: {
    fontWeight: '700',
    color: COLORS.blanco,
  },
  connectionStatusBox: {
    marginTop: 10,
    borderWidth: 1.5,
    borderRadius: 8,
    padding: 10,
  },
  connectionOkBox: {
    backgroundColor: COLORS.verdadero.bg,
    borderColor: COLORS.verdadero.borde,
  },
  connectionErrBox: {
    backgroundColor: COLORS.falso.bg,
    borderColor: COLORS.falso.borde,
  },
  connectionStatusText: {
    fontWeight: '600',
    lineHeight: 18,
  },
  modalBackdrop: {
    flex: 1,
    backgroundColor: 'rgba(0,0,0,0.55)',
    justifyContent: 'center',
    padding: 20,
  },
  modalCard: {
    backgroundColor: COLORS.blanco,
    borderRadius: 14,
    borderWidth: 2,
    borderColor: COLORS.falso.borde,
    padding: 18,
    gap: 12,
  },
  modalTitle: {
    fontWeight: '800',
    color: COLORS.falso.color,
  },
  modalDesc: {
    color: COLORS.tinta,
    lineHeight: 20,
  },
  modalErrorText: {
    color: COLORS.falso.color,
    fontWeight: '700',
  },
  modalActions: {
    flexDirection: 'row',
    gap: 10,
    marginTop: 4,
  },
  modalCancelBtn: {
    flex: 1,
    minHeight: 46,
    borderWidth: 1.5,
    borderColor: COLORS.borde,
    borderRadius: 8,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: COLORS.papel,
  },
  modalCancelText: {
    color: COLORS.tinta,
    fontWeight: '700',
  },
  modalDeleteBtn: {
    flex: 1,
    minHeight: 46,
    borderRadius: 8,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: COLORS.falso.color,
  },
  modalDeleteText: {
    color: COLORS.blanco,
    fontWeight: '800',
  },
});
