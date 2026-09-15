import React, {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useMemo,
  useState,
} from 'react';
import { ApiError, getStoredApiUrl, getStoredToken, setStoredToken } from '../api/client';
import { login as apiLogin, logout as apiLogout, me as apiMe, updateApiUrl } from '../api/endpoints';
import { CaixaResumido, ConfigPDV, Usuario } from '../types';

interface AuthContextData {
  loading: boolean;
  token: string | null;
  usuario: Usuario | null;
  config: ConfigPDV | null;
  caixa: CaixaResumido | null;
  apiUrl: string;
  signIn: (email: string, senha: string) => Promise<void>;
  signOut: () => Promise<void>;
  setServerUrl: (url: string) => Promise<void>;
  refreshCaixa: (caixa: CaixaResumido | null) => void;
  refreshMe: () => Promise<void>;
}

const AuthContext = createContext<AuthContextData>({} as AuthContextData);

export const AuthProvider: React.FC<{ children: React.ReactNode }> = ({ children }) => {
  const [loading, setLoading] = useState(true);
  const [token, setToken] = useState<string | null>(null);
  const [usuario, setUsuario] = useState<Usuario | null>(null);
  const [config, setConfig] = useState<ConfigPDV | null>(null);
  const [caixa, setCaixa] = useState<CaixaResumido | null>(null);
  const [apiUrl, setApiUrl] = useState('');

  useEffect(() => {
    (async () => {
      try {
        const [savedToken, savedUrl] = await Promise.all([getStoredToken(), getStoredApiUrl()]);
        setApiUrl(savedUrl);
        if (savedToken) {
          try {
            const data = await apiMe(savedToken);
            setToken(savedToken);
            setUsuario(data.usuario);
            setConfig(data.config);
            setCaixa(data.caixa);
          } catch (e) {
            if (e instanceof ApiError && e.unauthorized) {
              await setStoredToken(null);
            }
          }
        }
      } finally {
        setLoading(false);
      }
    })();
  }, []);

  const signIn = useCallback(async (email: string, senha: string) => {
    const data = await apiLogin(email, senha);
    await setStoredToken(data.token);
    setToken(data.token);
    setUsuario(data.usuario);
    setConfig(data.config);
    setCaixa(data.caixa);
  }, []);

  const signOut = useCallback(async () => {
    await apiLogout(token);
    await setStoredToken(null);
    setToken(null);
    setUsuario(null);
    setConfig(null);
    setCaixa(null);
  }, [token]);

  const setServerUrl = useCallback(async (url: string) => {
    await updateApiUrl(url);
    setApiUrl(url.replace(/\/+$/, ''));
  }, []);

  const refreshCaixa = useCallback((novoCaixa: CaixaResumido | null) => {
    setCaixa(novoCaixa);
  }, []);

  const refreshMe = useCallback(async () => {
    if (!token) return;
    try {
      const data = await apiMe(token);
      setUsuario(data.usuario);
      setConfig(data.config);
      setCaixa(data.caixa);
    } catch {
      // ignore
    }
  }, [token]);

  const value = useMemo(
    () => ({
      loading,
      token,
      usuario,
      config,
      caixa,
      apiUrl,
      signIn,
      signOut,
      setServerUrl,
      refreshCaixa,
      refreshMe,
    }),
    [loading, token, usuario, config, caixa, apiUrl, signIn, signOut, setServerUrl, refreshCaixa, refreshMe]
  );

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
};

export const useAuth = (): AuthContextData => useContext(AuthContext);