import React, { createContext, useContext, useState, useEffect } from 'react';
import {
  DEFAULT_SERVER_URL,
  obtenerToken,
  guardarToken,
  obtenerPerfilApi,
  loginApi,
  registerApi,
  logoutApi,
  initServerUrl,
  setServerUrl,
  normalizarUrlServidor,
} from '../services/api';

const AuthContext = createContext();

export function AuthProvider({ children }) {
  const [usuario, setUsuario] = useState(null);
  const [cargando, setCargando] = useState(true);
  const [urlServidor, setUrlServidor] = useState(DEFAULT_SERVER_URL);

  useEffect(() => {
    cargarSesion();
  }, []);

  const cargarSesion = async () => {
    setCargando(true);
    try {
      const url = await initServerUrl();
      setUrlServidor(url);

      const token = await obtenerToken();
      if (token) {
        try {
          const perfil = await obtenerPerfilApi();
          setUsuario(perfil.usuario || perfil);
        } catch (err) {
          if (err?.status === 401) {
            await guardarToken(null);
          }
        }
      }
    } catch {}
    setCargando(false);
  };

  const login = async (email, password) => {
    const res = await loginApi(email, password);
    setUsuario(res.usuario || { email });
    return res;
  };

  const registro = async (email, password, passwordConfirm) => {
    const res = await registerApi(email, password, passwordConfirm);
    setUsuario(res.usuario || { email });
    return res;
  };

  const logout = async () => {
    await logoutApi();
    setUsuario(null);
  };

  const limpiarSesionLocal = async () => {
    await guardarToken(null);
    setUsuario(null);
  };

  const actualizarUrlServidor = async (nuevaUrl) => {
    const limpia = await setServerUrl(normalizarUrlServidor(nuevaUrl));
    setUrlServidor(limpia);
    return limpia;
  };

  return (
    <AuthContext.Provider
      value={{
        usuario,
        cargando,
        login,
        registro,
        logout,
        limpiarSesionLocal,
        urlServidor,
        actualizarUrlServidor,
        estaAutenticado: !!usuario,
      }}
    >
      {children}
    </AuthContext.Provider>
  );
}

export const useAuth = () => useContext(AuthContext);
