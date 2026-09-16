import React, { useCallback, useEffect, useMemo, useState } from 'react';
import {
  ActivityIndicator,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  View,
} from 'react-native';
import { useNavigation } from '@react-navigation/native';
import { NativeStackNavigationProp } from '@react-navigation/native-stack';
import { Screen } from '../components/Screen';
import { AppButton } from '../components/AppButton';
import { AppInput } from '../components/AppInput';
import { SelectModal } from '../components/SelectModal';
import { useAuth } from '../contexts/AuthContext';
import { useToast } from '../contexts/ToastContext';
import { getRelatorio } from '../api/endpoints';
import { colors, radius, spacing, typography } from '../theme';
import { money, parseDateBR, dateBR } from '../utils/format';
import { RelatorioResponse } from '../types';
import { RootStackParamList } from '../navigation';

const STATUS_OPTIONS = [
  { valor: 'ativas', label: 'Vendas ativas' },
  { valor: 'todas', label: 'Todas (inclui canceladas)' },
  { valor: 'finalizada', label: 'Finalizadas' },
  { valor: 'pendente', label: 'Pendentes' },
  { valor: 'cancelada', label: 'Canceladas' },
];

const pad = (n: number) => String(n).padStart(2, '0');
const toISO = (d: Date) => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
const isoFromBR = (br: string): string => {
  const d = parseDateBR(br);
  return /^\d{4}-\d{2}-\d{2}$/.test(d) ? d : '';
};

interface Filtros {
  inicio: string;
  fim: string;
  status: string;
  metodo: string;
  vendedor: number;
}

export const RelatoriosScreen: React.FC = () => {
  const navigation = useNavigation<NativeStackNavigationProp<RootStackParamList>>();
  const { token, config } = useAuth();
  const { show } = useToast();

  const inicial = useMemo<Filtros>(() => {
    const hoje = new Date();
    const primeiro = new Date(hoje.getFullYear(), hoje.getMonth(), 1);
    return {
      inicio: toISO(primeiro),
      fim: toISO(hoje),
      status: 'ativas',
      metodo: '',
      vendedor: 0,
    };
  }, []);

  const [inicio, setInicio] = useState(dateBR(inicial.inicio));
  const [fim, setFim] = useState(dateBR(inicial.fim));
  const [status, setStatus] = useState(inicial.status);
  const [metodo, setMetodo] = useState(inicial.metodo);
  const [vendedor, setVendedor] = useState(inicial.vendedor);

  const [statusModal, setStatusModal] = useState(false);
  const [metodoModal, setMetodoModal] = useState(false);
  const [vendedorModal, setVendedorModal] = useState(false);

  const [data, setData] = useState<RelatorioResponse | null>(null);
  const [carregando, setCarregando] = useState(false);

  const metodos = useMemo(
    () => Object.entries(config?.metodos_pagamento ?? {}).map(([slug, label]) => ({ slug, label })),
    [config]
  );

  const consultar = useCallback(
    async (filtros: Filtros) => {
      if (!token) return;
      const i = isoFromBR(filtros.inicio);
      const f = isoFromBR(filtros.fim);
      if (!i || !f) {
        show('Informe datas válidas no formato dd/mm/aaaa.', 'error');
        return;
      }
      if (new Date(f).getTime() < new Date(i).getTime()) {
        show('A data final não pode ser anterior à inicial.', 'error');
        return;
      }
      setCarregando(true);
      try {
        const r = await getRelatorio(token, {
          inicio: i,
          fim: f,
          status: filtros.status,
          metodo: filtros.metodo || undefined,
          vendedor: filtros.vendedor || undefined,
        });
        setData(r);
      } catch (e) {
        show((e as Error).message, 'error');
      } finally {
        setCarregando(false);
      }
    },
    [token, show]
  );

  useEffect(() => {
    consultar({
      inicio: dateBR(inicial.inicio),
      fim: dateBR(inicial.fim),
      status: inicial.status,
      metodo: inicial.metodo,
      vendedor: inicial.vendedor,
    });
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  const aplicarPreset = (preset: 'hoje' | 'ontem' | '7dias' | 'mes' | 'mesPassado') => {
    const hoje = new Date();
    let de = new Date(hoje);
    let ate = new Date(hoje);

    if (preset === 'ontem') {
      de.setDate(de.getDate() - 1);
      ate = new Date(de);
    } else if (preset === '7dias') {
      de.setDate(de.getDate() - 6);
    } else if (preset === 'mes') {
      de = new Date(hoje.getFullYear(), hoje.getMonth(), 1);
    } else if (preset === 'mesPassado') {
      de = new Date(hoje.getFullYear(), hoje.getMonth() - 1, 1);
      ate = new Date(hoje.getFullYear(), hoje.getMonth(), 0);
    }

    const novoInicio = toISO(de);
    const novoFim = toISO(ate);
    setInicio(dateBR(novoInicio));
    setFim(dateBR(novoFim));
    consultar({ inicio: dateBR(novoInicio), fim: dateBR(novoFim), status, metodo, vendedor });
  };

  const consultarAtual = () => consultar({ inicio, fim, status, metodo, vendedor });

  const statusLabel = STATUS_OPTIONS.find((s) => s.valor === status)?.label ?? status;
  const metodoLabel = metodo
    ? metodos.find((m) => m.slug === metodo)?.label ?? metodo
    : 'Todos';
  const vendedorLabel = vendedor
    ? data?.opcoes.vendedores.find((v) => v.id === vendedor)?.nome ?? 'Selecionado'
    : 'Todos';

  return (
    <Screen padded={false}>
      <ScrollView contentContainerStyle={styles.content}>
        <Text style={styles.title}>Relatórios</Text>
        <Text style={styles.subtitle}>Consulta de vendas por período</Text>

        <View style={styles.card}>
          <View style={styles.chips}>
            {[
              { id: 'hoje', label: 'Hoje' },
              { id: 'ontem', label: 'Ontem' },
              { id: '7dias', label: '7 dias' },
              { id: 'mes', label: 'Este mês' },
              { id: 'mesPassado', label: 'Mês passado' },
            ].map((c) => (
              <Pressable key={c.id} style={styles.chip} onPress={() => aplicarPreset(c.id as never)}>
                <Text style={styles.chipText}>{c.label}</Text>
              </Pressable>
            ))}
          </View>

          <View style={styles.dateRow}>
            <AppInput
              label="Data inicial"
              value={inicio}
              onChangeText={setInicio}
              placeholder="dd/mm/aaaa"
              keyboardType="number-pad"
              style={styles.dateInput}
            />
            <AppInput
              label="Data final"
              value={fim}
              onChangeText={setFim}
              placeholder="dd/mm/aaaa"
              keyboardType="number-pad"
              style={styles.dateInput}
            />
          </View>

          <Text style={styles.filterLabel}>Filtros</Text>
          <AppButton
            title={`Status: ${statusLabel}`}
            variant="outline"
            small
            onPress={() => setStatusModal(true)}
          />
          <AppButton
            title={`Pagamento: ${metodoLabel}`}
            variant="outline"
            small
            onPress={() => setMetodoModal(true)}
          />
          <AppButton
            title={`Vendedor: ${vendedorLabel}`}
            variant="outline"
            small
            onPress={() => setVendedorModal(true)}
          />

          <AppButton title={carregando ? 'Consultando...' : 'Consultar'} onPress={consultarAtual} loading={carregando} />
        </View>

        {carregando && !data && <ActivityIndicator color={colors.primary} style={styles.loader} />}

        {data && (
          <>
            <Text style={styles.periodo}>
              Período: {data.periodo.inicio_formatado} a {data.periodo.fim_formatado}
            </Text>

            <View style={styles.kpiGrid}>
              <Kpi label="Faturamento" value={data.kpis.faturamento_formatado} destaque />
              <Kpi label="Vendas" value={String(data.kpis.vendas)} />
              <Kpi label="Ticket médio" value={data.kpis.ticket_medio_formatado} />
              <Kpi label="Itens vendidos" value={String(data.kpis.itens)} />
              <Kpi label="Descontos" value={data.kpis.descontos_formatado} />
              <Kpi label="Canceladas" value={`${data.canceladas.n} · ${data.canceladas.valor_formatado}`} />
            </View>

            <Secao titulo="Crediário (a prazo)">
              <Linha label="Parcelas geradas" value={String(data.crediario.parcelas)} />
              <Linha label="Valor das parcelas" value={data.crediario.a_receber_formatado} />
              <Linha label="Recebido no período" value={data.crediario.recebido_formatado} />
            </Secao>

            <Secao titulo="Formas de pagamento">
              {data.por_metodo.length === 0 && <Vazio />}
              {data.por_metodo.map((m) => (
                <Linha
                  key={m.metodo}
                  label={`${m.metodo_label} (${m.vendas})`}
                  value={`${m.total_formatado}${m.pendente > 0 ? ` · pend. ${m.pendente_formatado}` : ''}`}
                />
              ))}
            </Secao>

            <Secao titulo="Vendas por vendedor">
              {data.por_vendedor.length === 0 && <Vazio />}
              {data.por_vendedor.map((v, i) => (
                <Linha
                  key={`${v.vendedor}-${i}`}
                  label={`${v.vendedor} (${v.vendas})`}
                  value={v.valor_formatado}
                />
              ))}
            </Secao>

            <Secao titulo="Produtos mais vendidos">
              {data.top_produtos.length === 0 && <Vazio />}
              {data.top_produtos.map((p) => (
                <Linha key={p.nome} label={`${p.quantidade}x ${p.nome}`} value={p.valor_formatado} />
              ))}
            </Secao>

            <Secao titulo="Top clientes">
              {data.top_clientes.length === 0 && <Vazio />}
              {data.top_clientes.map((c, i) => (
                <Linha key={`${c.cliente}-${i}`} label={`${c.cliente} (${c.vendas})`} value={c.valor_formatado} />
              ))}
            </Secao>

            <Secao titulo="Vendas do período">
              {data.vendas.length === 0 && <Vazio />}
              {data.vendas.map((v) => (
                <Pressable
                  key={v.id}
                  style={styles.vendaRow}
                  onPress={() => navigation.navigate('VendaDetalhe', { vendaId: v.id })}
                >
                  <View style={styles.vendaInfo}>
                    <Text style={styles.vendaNumero}>#{v.numero}</Text>
                    <Text style={styles.vendaMeta}>
                      {v.cliente || 'Sem cliente'} · {v.status}
                    </Text>
                  </View>
                  <Text style={styles.vendaTotal}>{v.total_formatado}</Text>
                </Pressable>
              ))}
            </Secao>
          </>
        )}
      </ScrollView>

      <SelectModal
        visible={statusModal}
        title="Status das vendas"
        options={STATUS_OPTIONS.map((s) => ({ id: STATUS_OPTIONS.indexOf(s), label: s.label, value: s.valor }))}
        selectedId={STATUS_OPTIONS.findIndex((s) => s.valor === status)}
        onSelect={(o) => {
          setStatus(String(o.value));
          setStatusModal(false);
        }}
        onClose={() => setStatusModal(false)}
      />
      <SelectModal
        visible={metodoModal}
        title="Forma de pagamento"
        options={[
          { id: -1, label: 'Todos', value: '' },
          ...metodos.map((m, i) => ({ id: i, label: m.label, value: m.slug })),
        ]}
        selectedId={metodo ? metodos.findIndex((m) => m.slug === metodo) : -1}
        onSelect={(o) => {
          setMetodo(String(o.value));
          setMetodoModal(false);
        }}
        onClose={() => setMetodoModal(false)}
      />
      <SelectModal
        visible={vendedorModal}
        title="Vendedor"
        options={[
          { id: 0, label: 'Todos', value: 0 },
          ...(data?.opcoes.vendedores ?? []).map((v) => ({ id: v.id, label: v.nome, value: v.id })),
        ]}
        selectedId={vendedor}
        onSelect={(o) => {
          setVendedor(Number(o.value));
          setVendedorModal(false);
        }}
        onClose={() => setVendedorModal(false)}
      />
    </Screen>
  );
};

const Kpi: React.FC<{ label: string; value: string; destaque?: boolean }> = ({
  label,
  value,
  destaque,
}) => (
  <View style={[styles.kpi, destaque && styles.kpiDestaque]}>
    <Text style={styles.kpiLabel}>{label}</Text>
    <Text style={[styles.kpiValue, destaque && styles.kpiValueDestaque]} numberOfLines={1}>
      {value}
    </Text>
  </View>
);

const Secao: React.FC<{ titulo: string; children: React.ReactNode }> = ({ titulo, children }) => (
  <View style={styles.secao}>
    <Text style={styles.secaoTitulo}>{titulo}</Text>
    <View style={styles.card}>{children}</View>
  </View>
);

const Linha: React.FC<{ label: string; value: string }> = ({ label, value }) => (
  <View style={styles.line}>
    <Text style={styles.lineLabel} numberOfLines={1}>
      {label}
    </Text>
    <Text style={styles.lineValue}>{value}</Text>
  </View>
);

const Vazio: React.FC = () => <Text style={styles.vazio}>Nenhum registro no período.</Text>;

const styles = StyleSheet.create({
  content: { padding: spacing.lg, gap: spacing.md, paddingBottom: 40 },
  title: { fontSize: typography.h1, fontWeight: '800', color: colors.ink },
  subtitle: { fontSize: typography.small, color: colors.muted, marginTop: -6 },
  card: {
    backgroundColor: colors.surface,
    borderRadius: radius.md,
    padding: spacing.md,
    gap: spacing.sm,
  },
  chips: { flexDirection: 'row', flexWrap: 'wrap', gap: spacing.sm },
  chip: {
    paddingHorizontal: spacing.md,
    paddingVertical: 7,
    borderRadius: radius.full,
    backgroundColor: colors.primaryLight,
  },
  chipText: { color: colors.primaryDark, fontWeight: '700', fontSize: typography.small },
  dateRow: { flexDirection: 'row', gap: spacing.sm },
  dateInput: { flex: 1, marginBottom: 0 },
  filterLabel: {
    fontSize: typography.small,
    fontWeight: '800',
    color: colors.muted,
    marginTop: spacing.xs,
  },
  loader: { marginTop: spacing.lg },
  periodo: { fontSize: typography.small, color: colors.muted, fontWeight: '700' },
  kpiGrid: { flexDirection: 'row', flexWrap: 'wrap', gap: spacing.md },
  kpi: {
    width: '47%',
    backgroundColor: colors.surface,
    borderRadius: radius.md,
    padding: spacing.md,
  },
  kpiDestaque: { backgroundColor: colors.primaryLight },
  kpiLabel: { fontSize: typography.tiny, color: colors.textLight, textTransform: 'uppercase' },
  kpiValue: { fontSize: typography.h3, fontWeight: '800', color: colors.ink, marginTop: 2 },
  kpiValueDestaque: { color: colors.primaryDark },
  secao: { gap: spacing.xs },
  secaoTitulo: { fontSize: typography.h3, fontWeight: '800', color: colors.ink },
  line: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', paddingVertical: 3 },
  lineLabel: { flex: 1, paddingRight: spacing.sm, fontSize: typography.body, color: colors.muted },
  lineValue: { fontSize: typography.body, color: colors.ink, fontWeight: '600' },
  vazio: { fontSize: typography.small, color: colors.textLight, paddingVertical: 4 },
  vendaRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    paddingVertical: 8,
    borderBottomWidth: StyleSheet.hairlineWidth,
    borderBottomColor: colors.border,
  },
  vendaInfo: { flex: 1, paddingRight: spacing.sm },
  vendaNumero: { fontSize: typography.body, fontWeight: '700', color: colors.ink },
  vendaMeta: { fontSize: typography.tiny, color: colors.textLight, marginTop: 1 },
  vendaTotal: { fontSize: typography.body, fontWeight: '700', color: colors.primaryDark },
});
