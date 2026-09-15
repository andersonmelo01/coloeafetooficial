import * as SecureStore from 'expo-secure-store';

const TOKEN_KEY = 'pdv_mobile_token';
const URL_KEY = 'pdv_mobile_api_url';
export const DEFAULT_API_URL = 'https://coloeafetooficial.com.br/api/pdv';

export class ApiError extends Error {
  status: number;
  unauthorized: boolean;

  constructor(message: string, status: number, unauthorized = false) {
    super(message);
    this.name = 'ApiError';
    this.status = status;
    this.unauthorized = unauthorized;
  }
}

export async function getStoredToken(): Promise<string | null> {
  try {
    return await SecureStore.getItemAsync(TOKEN_KEY);
  } catch {
    return null;
  }
}

export async function setStoredToken(token: string | null): Promise<void> {
  try {
    if (token) {
      await SecureStore.setItemAsync(TOKEN_KEY, token);
    } else {
      await SecureStore.deleteItemAsync(TOKEN_KEY);
    }
  } catch {
    // ignore
  }
}

export async function getStoredApiUrl(): Promise<string> {
  try {
    return (await SecureStore.getItemAsync(URL_KEY)) ?? DEFAULT_API_URL;
  } catch {
    return DEFAULT_API_URL;
  }
}

export async function setStoredApiUrl(url: string): Promise<void> {
  try {
    await SecureStore.setItemAsync(URL_KEY, url);
  } catch {
    // ignore
  }
}

type Options = {
  method?: 'GET' | 'POST';
  body?: unknown;
  token?: string | null;
};

async function request<T>(path: string, options: Options = {}): Promise<T> {
  const baseUrl = (await getStoredApiUrl()).replace(/\/+$/, '');
  const { method = 'GET', body, token } = options;

  let response: Response;
  try {
    response = await fetch(`${baseUrl}${path}`, {
      method,
      headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        ...(token ? { Authorization: `Bearer ${token}` } : {}),
      },
      body: body !== undefined ? JSON.stringify(body) : undefined,
    });
  } catch (e) {
    throw new ApiError(
      'Não foi possível conectar ao servidor. Verifique a URL do sistema e sua conexão.',
      0
    );
  }

  let payload: any = null;
  try {
    payload = await response.json();
  } catch {
    // ignore invalid json
  }

  if (!response.ok) {
    const msg = payload?.error || `Erro do servidor (${response.status}).`;
    throw new ApiError(msg, response.status, response.status === 401);
  }

  return payload as T;
}

export function get<const T>(path: string, token?: string | null): Promise<T> {
  return request<T>(path, { token });
}

export function post<const T>(path: string, body: unknown, token?: string | null): Promise<T> {
  return request<T>(path, { method: 'POST', body, token });
}