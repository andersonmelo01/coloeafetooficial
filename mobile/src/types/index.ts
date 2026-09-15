export type MetodoPagamento = 'dinheiro' | 'pix' | 'cartao_credito' | 'cartao_debito' | 'a_prazo';

export interface Usuario {
  id: number;
  nome: string;
  email: string;
  tipo: string;
}

export interface ConfigPDV {
  controle_estoque: boolean;
  fiscal_habilitado: boolean;
  efi_pix: boolean;
  efi_pix_automatico: boolean;
  efi_cartao: boolean;
  efi_cartao_automatico: boolean;
  metodos_pagamento: Record<string, string>;
  nome_estabelecimento: string;
}

export interface CaixaResumido {
  id: number;
  aberto_em: string;
  saldo: number;
}

export interface LoginResponse {
  ok: true;
  token: string;
  usuario: Usuario;
  config: ConfigPDV;
  caixa: CaixaResumido | null;
}

export interface MeResponse {
  ok: true;
  usuario: Usuario;
  config: ConfigPDV;
  caixa: CaixaResumido | null;
}

export interface Produto {
  id: number;
  nome: string;
  sku: string;
  descricao_curta: string;
  categoria: string;
  grupo: string;
  preco: number;
  preco_atual: number;
  promocional: boolean;
  estoque: number;
  controle_estoque: boolean;
  sem_estoque: boolean;
  destaque: boolean;
  imagem: string | null;
  preco_formatado: string;
  preco_original_formatado: string;
}

export interface ProdutosResponse {
  ok: true;
  produtos: Produto[];
  total: number;
  pagina: number;
  pages: number;
  per_page: number;
}

export interface Categoria {
  id: number;
  nome: string;
  total_produtos: number;
}

export interface Cliente {
  id: number;
  nome: string;
  email: string;
  label: string;
}

export interface CaixaMovimento {
  id: number;
  caixa_id: number;
  venda_id: number | null;
  venda_numero: string;
  tipo: 'entrada' | 'saida' | 'sangria';
  metodo: string;
  metodo_label: string;
  valor: number;
  valor_formatado: string;
  observacao: string;
  usuario: string;
  criado_em: string;
}

export interface ResumoMetodo {
  metodo: string;
  metodo_label: string;
  valor: number;
  valor_formatado: string;
}

export interface CaixaAnterior {
  id: number;
  aberto_em: string;
  fechado_em: string | null;
  usuario: string;
  saldo_inicial: number;
  saldo_final: number | null;
  total_entradas: number;
  total_saidas: number;
  status: string;
  observacao: string;
}

export interface CaixaTotais {
  saldo_inicial: number;
  vendas: number;
  entradas: number;
  sangrias: number;
  saidas: number;
  saldo: number;
}

export interface CaixaResponse {
  ok: true;
  caixa: { id: number; aberto_em: string; observacao: string } | null;
  totais: CaixaTotais;
  totais_formatados: Record<string, string>;
  movimentos: CaixaMovimento[];
  resumo_por_metodo: ResumoMetodo[];
  caixas_anteriores: CaixaAnterior[];
}

export interface VendaItem {
  id?: number;
  produto_id: number | null;
  nome: string;
  quantidade: number;
  preco_unitario: number;
  preco_unitario_formatado?: string;
  total: number;
  total_formatado?: string;
}

export interface VendaLista {
  id: number;
  numero: string;
  status: 'finalizada' | 'pendente' | 'cancelada' | 'aberta';
  total: number;
  total_formatado: string;
  cliente: string;
  vendedor: string;
  data: string;
}

export interface PagamentoInfo {
  id: number;
  metodo: string;
  metodo_label: string;
  valor: number;
  valor_formatado: string;
  status: string;
  pago_em: string | null;
}

export interface Parcela {
  id: number;
  parcela: number;
  vencimento: string;
  valor: number;
  valor_formatado: string;
  status: string;
  pago_em: string | null;
}

export interface VendaDetalhe {
  id: number;
  numero: string;
  status: string;
  subtotal: number;
  desconto: number;
  total: number;
  subtotal_formatado: string;
  desconto_formatado: string;
  total_formatado: string;
  observacao: string;
  vendedor: string;
  cliente: string;
  cliente_email: string;
  email_recibo: string;
  cupom_no: string;
  nfce_chave: string;
  finalizada_em: string;
  cancelada_em: string | null;
  motivo_cancelamento: string;
  pix_copiaecola: string;
  pix_qrcode: string;
  pix_txid: string;
  tem_pendente_online: boolean;
}

export interface VendaDetalheResponse {
  ok: true;
  venda: VendaDetalhe;
  itens: VendaItem[];
  pagamentos: PagamentoInfo[];
  parcelas: Parcela[];
  total_parcelas: number;
}

export interface VendaCriada {
  id: number;
  numero: string;
  subtotal: number;
  desconto: number;
  total: number;
  subtotal_formatado: string;
  desconto_formatado: string;
  total_formatado: string;
  status: string;
  restante_aprazo: number;
  restante_aprazo_formatado: string;
  observacao: string;
  finalizada_em: string;
}

export interface VendaCriadaResponse {
  ok: true;
  venda: VendaCriada;
  pagamento_pendente_online: boolean;
  aviso_integracao: string;
  pix: { copiaecola: string; qrcode_base64: string } | null;
  sem_caixa: boolean;
  mensagens: { tipo: string; texto: string }[];
}

export interface PagamentoEnvio {
  metodo: MetodoPagamento;
  valor: number;
  parcelas: number;
  vencimento?: string;
  token?: string;
}

export interface ItemEnvio {
  produto_id: number;
  quantidade: number;
}

export interface VendaEnvio {
  cliente_id?: number;
  email_recibo?: string;
  desconto?: number;
  observacao?: string;
  itens: ItemEnvio[];
  pagamentos: PagamentoEnvio[];
}

export interface CartItem {
  produto_id: number;
  nome: string;
  preco: number;
  quantidade: number;
  imagem: string | null;
  estoque: number;
  controle_estoque: boolean;
}