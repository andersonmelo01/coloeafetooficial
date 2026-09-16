import { get, post, setStoredApiUrl, getStoredApiUrl } from './client';
import {
  CaixaResponse,
  Categoria,
  Cliente,
  LoginResponse,
  MeResponse,
  ProdutosResponse,
  RelatorioResponse,
  VendaCriadaResponse,
  VendaDetalheResponse,
  VendaEnvio,
  VendaLista,
} from '../types';

export async function login(email: string, senha: string): Promise<LoginResponse> {
  return post<LoginResponse>('/login.php', { email, senha });
}

export async function logout(token: string | null): Promise<void> {
  try {
    await post<{ ok: true }>('/logout.php', {}, token);
  } catch {
    // ignore network errors on logout
  }
}

export async function me(token: string | null): Promise<MeResponse> {
  return get<MeResponse>('/me.php', token);
}

export async function getProdutos(
  token: string | null,
  params: { q?: string; categoria?: number; pagina?: number; per_page?: number }
): Promise<ProdutosResponse> {
  const qs = new URLSearchParams();
  if (params.q) qs.set('q', params.q);
  if (params.categoria && params.categoria > 0) qs.set('categoria', String(params.categoria));
  if (params.pagina) qs.set('pagina', String(params.pagina));
  if (params.per_page) qs.set('per_page', String(params.per_page));
  const suffix = qs.toString() ? `?${qs.toString()}` : '';
  return get<ProdutosResponse>(`/produtos.php${suffix}`, token);
}

export async function getCategorias(token: string | null): Promise<{ ok: true; categorias: Categoria[] }> {
  return get(`/categorias.php`, token);
}

export async function getClientes(
  token: string | null,
  q = ''
): Promise<{ ok: true; clientes: Cliente[] }> {
  const suffix = q ? `?q=${encodeURIComponent(q)}` : '';
  return get(`/clientes.php${suffix}`, token);
}

export async function getCaixa(token: string | null): Promise<CaixaResponse> {
  return get<CaixaResponse>('/caixa.php', token);
}

export async function caixaAction(
  token: string | null,
  body: Record<string, unknown>
): Promise<{ ok: true; message: string; saldo_final?: number }> {
  return post(`/caixa.php`, body, token);
}

export async function createVenda(
  token: string | null,
  body: VendaEnvio
): Promise<VendaCriadaResponse> {
  return post<VendaCriadaResponse>('/venda.php', body, token);
}

export async function getVendas(
  token: string | null,
  params: { status?: string; pagina?: number }
): Promise<{ ok: true; vendas: VendaLista[]; total: number; pagina: number; pages: number }> {
  const qs = new URLSearchParams();
  if (params.status) qs.set('status', params.status);
  if (params.pagina) qs.set('pagina', String(params.pagina));
  const suffix = qs.toString() ? `?${qs.toString()}` : '';
  return get(`/historico.php${suffix}`, token);
}

export async function getVenda(
  token: string | null,
  id: number
): Promise<VendaDetalheResponse> {
  return get<VendaDetalheResponse>(`/historico.php?venda=${id}`, token);
}

export async function cancelarVenda(
  token: string | null,
  vendaId: number,
  motivo: string
): Promise<{ ok: true; message: string }> {
  return post('/cancelar.php', { venda_id: vendaId, motivo_cancelamento: motivo }, token);
}

export async function enviarCupom(
  token: string | null,
  vendaId: number,
  email: string
): Promise<{ ok: true; message: string }> {
  return post('/cupom.php', { venda_id: vendaId, email }, token);
}

export async function getRelatorio(
  token: string | null,
  params: {
    inicio: string;
    fim: string;
    status?: string;
    metodo?: string;
    vendedor?: number;
    cliente?: number;
  }
): Promise<RelatorioResponse> {
  const qs = new URLSearchParams();
  qs.set('inicio', params.inicio);
  qs.set('fim', params.fim);
  if (params.status) qs.set('status', params.status);
  if (params.metodo) qs.set('metodo', params.metodo);
  if (params.vendedor) qs.set('vendedor', String(params.vendedor));
  if (params.cliente !== undefined) qs.set('cliente', String(params.cliente));
  return get<RelatorioResponse>(`/relatorio.php?${qs.toString()}`, token);
}

export async function updateApiUrl(url: string): Promise<void> {
  await setStoredApiUrl(url);
}

export async function getApiUrl(): Promise<string> {
  return getStoredApiUrl();
}