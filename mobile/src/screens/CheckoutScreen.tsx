import React, { useEffect, useMemo, useState } from 'react';
import {
  KeyboardAvoidingView,
  Platform,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  View,
} from 'react-native';
import { useNavigation } from '@react-navigation/native';
import { NativeStackNavigationProp } from '@react-navigation/native-stack';
import { Ionicons } from '@expo/vector-icons';
import { Screen } from '../components/Screen';
import { AppButton } from '../components/AppButton';
import { AppInput } from '../components/AppInput';
import { MoneyInput } from '../components/MoneyInput';
import { SelectModal } from '../components/SelectModal';
import { useAuth } from '../contexts/AuthContext';
import { useCart } from '../contexts/CartContext';
import { useToast } from '../contexts/ToastContext';
import { getClientes, createVenda } from '../api/endpoints';
import { colors, radius, spacing, typography } from '../theme';
import { money, parseDateBR, daysFromNow, dateBR } from '../utils/format';
import { Cliente, MetodoPagamento, PagamentoEnvio, VendaEnvio } from '../types';
import { RootStackParamList } from '../navigation';

interface PagamentoRow {
  key: string;
  metodo: string;
  valor: number;
  parcelas: number;
  vencimento: string;
}

const novoRow = (metodo: string, valor = 0): PagamentoRow => ({
  key: `${Date.now()}-${Math.random().toString(36).slice(2, 8)}`,
  metodo,
  valor,
  parcelas: 1,
  vencimento: dateBR(daysFromNow(30)),
});

export const CheckoutScreen: React.FC = () => {
  const navigation = useNavigation<NativeStackNavigationProp<RootStackParamList>>();
  const { token, config } = useAuth();
  const { items, subtotal, clear } = useCart();
  const { show } = useToast();

  const [clienteId, setClienteId] = useState<number | null>(null);
  const [clientes, setClientes] = useState<Cliente[]>([]);
  const [clienteModal, setClienteModal] = useState(false);
  const [buscaCliente, setBuscaCliente] = useState('');

  const metodos = useMemo<{ id: string; label: string }[]>(
    () => Object.entries(config?.metodos_pagamento ?? {}).map(([id, label]) => ({ id, label })),
    [config]
  );
  const labelMetodo = (metodo: string) =>
    metodos.find((m) => m.id === metodo)?.label ?? metodo;

  const [desconto, setDesconto] = useState(0);
  const [emailRecibo, setEmailRecibo] = useState('');
  const [observacao, setObservacao] = useState('');

  const [pagamentos, setPagamentos] = useState<PagamentoRow[]>([novoRow('pix')]);
  const [valorManual, setValorManual] = useState(false);
  const [metodoModalKey, setMetodoModalKey] = useState<string | null>(null);
  const [parcelaModalKey, setParcelaModalKey] = useState<string | null>(null);

  const descontoAplicado = Math.min(desconto, subtotal);
  const total = Math.round((subtotal - descontoAplicado) * 100) / 100;
  const somaPagamentos = Math.round(
    pagamentos.reduce((s, p) => s + (p.valor > 0 ? p.valor : 0), 0) * 100
  ) / 100;
  const temPrazo = pagamentos.some((p) => p.metodo === 'a_prazo');
  const saldoPrazo = Math.max(0, Math.round((total - somaPagamentos) * 100) / 100);
  const troco = !temPrazo ? Math.round((somaPagamentos - total) * 100) / 100 : 0;

  useEffect(() => {
    if (!token) return;
    getClientes(token, buscaCliente)
      .then((r) => setClientes(r.clientes))
      .catch(() => {});
  }, [token, buscaCliente]);

  useEffect(() => {
    if (valorManual) return;
    setPagamentos((prev) => {
      if (prev.length !== 1) return prev;
      const row = prev[0];
      if (row.metodo === 'a_prazo') return prev;
      if (Math.abs(row.valor - total) < 0.005) return prev;
      return [{ ...row, valor: total }];
    });
  }, [total, valorManual]);

  const addRow = () => {
    setValorManual(true);
    setPagamentos((prev) => [...prev, novoRow('dinheiro')]);
  };

  const removeRow = (key: string) => {
    setPagamentos((prev) => (prev.length > 1 ? prev.filter((p) => p.key !== key) : prev));
  };

  const updateRow = (key: string, patch: Partial<PagamentoRow>) => {
    setPagamentos((prev) => prev.map((p) => (p.key === key ? { ...p, ...patch } : p)));
  };

  const updateValor = (key: string, valor: number) => {
    setValorManual(true);
    updateRow(key, { valor });
  };

  const trocarMetodo = (key: string, metodo: string) => {
    setPagamentos((prev) =>
      prev.map((p) =>
        p.key === key
          ? {
              ...p,
              metodo,
              valor: metodo === 'a_prazo' ? 0 : p.valor,
              parcelas: metodo === 'a_prazo' || metodo === 'cartao_credito' ? Math.max(1, p.parcelas) : 1,
            }
          : p
      )
    );
    if (metodo === 'a_prazo') {
      setValorManual(true);
    } else if (pagamentos.length === 1) {
      setValorManual(false);
    }
  };

  const confirmar = async () => {
    if (!token) return;
    if (items.length === 0) {
      show('Carrinho vazio', 'error');
      return;
    }

    let rows = pagamentos;

    if (temPrazo) {
      if (!clienteId) {
        show('A venda a prazo exige a seleção de um cliente cadastrado.', 'error');
        return;
      }
      const prazoRow = rows.find((p) => p.metodo === 'a_prazo');
      if (prazoRow && prazoRow.valor <= 0) {
        show('Informe o valor da entrada da venda a prazo.', 'error');
        return;
      }
    } else if (somaPagamentos < total) {
      const idx = rows.findIndex((p) => p.valor <= 0);
      if (idx >= 0) {
        const faltante = Math.round((total - somaPagamentos) * 100) / 100;
        rows = rows.map((p, i) => (i === idx ? { ...p, valor: faltante } : p));
      }
    }

    const somaFinal = Math.round(
      rows.reduce((s, p) => s + (p.valor > 0 ? p.valor : 0), 0) * 100
    ) / 100;
    if (!temPrazo && somaFinal < total) {
      show(
        `O valor dos pagamentos (${money(somaFinal)}) é menor que o total da venda (${money(total)}).`,
        'error'
      );
      return;
    }

    const pagamentosEnvio: PagamentoEnvio[] = rows
      .filter((p) => p.valor > 0)
      .map((p) => ({
        metodo: p.metodo as MetodoPagamento,
        valor: Math.round(p.valor * 100) / 100,
        parcelas:
          p.metodo === 'a_prazo' || p.metodo === 'cartao_credito' ? Math.max(1, p.parcelas) : 1,
        ...(p.metodo === 'a_prazo' && p.vencimento.trim() !== ''
          ? { vencimento: parseDateBR(p.vencimento) }
          : {}),
      }));

    if (pagamentosEnvio.length === 0) {
      show('Informe ao menos um pagamento válido.', 'error');
      return;
    }

    const body: VendaEnvio = {
      itens: items.map((i) => ({ produto_id: i.produto_id, quantidade: i.quantidade })),
      pagamentos: pagamentosEnvio,
      ...(descontoAplicado > 0 ? { desconto: descontoAplicado } : {}),
      ...(clienteId ? { cliente_id: clienteId } : {}),
      ...(emailRecibo.trim() !== '' ? { email_recibo: emailRecibo.trim() } : {}),
      ...(observacao.trim() !== '' ? { observacao: observacao.trim() } : {}),
    };

    try {
      const r = await createVenda(token, body);
      clear();
      navigation.navigate('Result', { vendaId: r.venda.id });
    } catch (e) {
      show((e as Error).message ?? 'Erro ao finalizar venda', 'error');
    }
  };

  const parcelaRow = pagamentos.find((p) => p.key === parcelaModalKey) ?? null;
  const baseParcela =
    parcelaRow?.metodo === 'a_prazo' ? saldoPrazo : parcelaRow?.valor ?? 0;

  return (
    <Screen>
      <KeyboardAvoidingView
        style={styles.flex}
        behavior={Platform.OS === 'ios' ? 'padding' : undefined}
      >
        <ScrollView contentContainerStyle={styles.content}>
          <Text style={styles.title}>Pagamento</Text>

          <AppButton
            title={clienteId ? 'Cliente selecionado' : 'Identificar cliente'}
            variant="outline"
            onPress={() => setClienteModal(true)}
            small
          />
          <Text style={styles.fieldHint}>Obrigatório para venda a prazo / crediário</Text>

          <Text style={styles.section}>Itens da venda</Text>
          <View style={styles.card}>
            {items.map((it) => (
              <View key={String(it.produto_id)} style={styles.itemLine}>
                <Text style={styles.itemName} numberOfLines={1}>
                  {it.quantidade}x {it.nome}
                </Text>
                <Text style={styles.itemValue}>{money(it.preco * it.quantidade)}</Text>
              </View>
            ))}
            <View style={styles.divider} />
            <View style={styles.line}>
              <Text style={styles.lineLabel}>Subtotal</Text>
              <Text style={styles.lineValue}>{money(subtotal)}</Text>
            </View>
            <MoneyInput label="Desconto" value={desconto} onValueChange={setDesconto} />
            <View style={styles.line}>
              <Text style={styles.totalLabel}>Total a receber</Text>
              <Text style={styles.total}>{money(total)}</Text>
            </View>
          </View>

          <View style={styles.sectionRow}>
            <Text style={styles.section}>Pagamento</Text>
            <Pressable style={styles.addBtn} onPress={addRow}>
              <Ionicons name="add-circle-outline" size={18} color={colors.primary} />
              <Text style={styles.addText}>Forma adicional</Text>
            </Pressable>
          </View>

          {pagamentos.map((p, index) => (
            <View key={p.key} style={styles.payCard}>
              <View style={styles.payHead}>
                <Text style={styles.payIndex}>Pagamento {index + 1}</Text>
                {pagamentos.length > 1 && (
                  <Pressable onPress={() => removeRow(p.key)} hitSlop={10}>
                    <Ionicons name="close-circle-outline" size={20} color={colors.danger} />
                  </Pressable>
                )}
              </View>

              <AppButton
                title={labelMetodo(p.metodo)}
                variant="outline"
                onPress={() => setMetodoModalKey(p.key)}
                small
              />

              <MoneyInput
                label={p.metodo === 'a_prazo' ? 'Valor pago agora (entrada)' : 'Valor'}
                value={p.valor}
                onValueChange={(v) => updateValor(p.key, v)}
              />

              {(p.metodo === 'a_prazo' || p.metodo === 'cartao_credito') && (
                <AppButton
                  title={
                    p.metodo === 'a_prazo'
                      ? `${p.parcelas}x no crediário`
                      : `${p.parcelas}x no cartão`
                  }
                  variant="ghost"
                  onPress={() => setParcelaModalKey(p.key)}
                  small
                />
              )}

              {p.metodo === 'a_prazo' && (
                <>
                  <AppInput
                    label="Vencimento da 1ª parcela"
                    value={p.vencimento}
                    onChangeText={(t) => updateRow(p.key, { vencimento: t })}
                    placeholder="dd/mm/aaaa"
                  />
                  <Text style={styles.hint}>
                    Informe o valor pago agora (entrada). O restante vira {p.parcelas} parcela(s)
                    automaticamente no crediário.
                  </Text>
                </>
              )}
            </View>
          ))}

          <Text style={styles.section}>Resumo</Text>
          <View style={styles.card}>
            <View style={styles.line}>
              <Text style={styles.lineLabel}>Pago agora</Text>
              <Text style={styles.lineValue}>{money(somaPagamentos)}</Text>
            </View>
            {temPrazo && (
              <View style={styles.line}>
                <Text style={styles.lineLabel}>Saldo a prazo</Text>
                <Text style={styles.warnValue}>{money(saldoPrazo)}</Text>
              </View>
            )}
            {!temPrazo && troco > 0 && (
              <View style={styles.line}>
                <Text style={styles.lineLabel}>Troco estimado</Text>
                <Text style={styles.successValue}>{money(troco)}</Text>
              </View>
            )}
          </View>

          <AppInput
            label="E-mail para o recibo (opcional)"
            value={emailRecibo}
            onChangeText={setEmailRecibo}
            placeholder="cliente@email.com"
            keyboardType="email-address"
            autoCapitalize="none"
          />
          <AppInput
            label="Observação (opcional)"
            value={observacao}
            onChangeText={setObservacao}
            placeholder="Ex.: retirada em 2 dias"
            multiline
          />
        </ScrollView>

        <View style={styles.footer}>
          <AppButton title="Confirmar venda" onPress={confirmar} />
        </View>

        <SelectModal
          visible={clienteModal}
          title="Selecionar cliente"
          options={clientes.map((c) => ({ id: c.id, label: `#${c.id} — ${c.nome}` }))}
          selectedId={clienteId}
          onSelect={(o) => {
            setClienteId(o.id);
            setClienteModal(false);
          }}
          onClose={() => setClienteModal(false)}
          searchable
        />
        <SelectModal
          visible={metodoModalKey !== null}
          title="Forma de pagamento"
          options={metodos.map((m, i) => ({ id: i, label: m.label, value: m.id }))}
          selectedId={null}
          onSelect={(o) => {
            if (metodoModalKey) trocarMetodo(metodoModalKey, String(o.value));
            setMetodoModalKey(null);
          }}
          onClose={() => setMetodoModalKey(null)}
        />
        <SelectModal
          visible={parcelaModalKey !== null}
          title={parcelaRow?.metodo === 'a_prazo' ? 'Parcelas futuras (crediário)' : 'Parcelas no cartão'}
          options={[1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12].map((n) => ({
            id: n,
            label: `${n}x de ${money(baseParcela / n)}`,
          }))}
          selectedId={parcelaRow?.parcelas ?? 1}
          onSelect={(o) => {
            if (parcelaModalKey) updateRow(parcelaModalKey, { parcelas: o.id });
            setParcelaModalKey(null);
          }}
          onClose={() => setParcelaModalKey(null)}
        />
      </KeyboardAvoidingView>
    </Screen>
  );
};

const styles = StyleSheet.create({
  flex: { flex: 1 },
  content: {
    padding: spacing.lg,
    gap: spacing.md,
    paddingBottom: 24,
  },
  title: {
    fontSize: typography.h1,
    fontWeight: '800',
    color: colors.ink,
  },
  section: {
    fontSize: typography.small,
    fontWeight: '800',
    color: colors.muted,
    marginTop: spacing.sm,
  },
  sectionRow: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    marginTop: spacing.sm,
  },
  addBtn: { flexDirection: 'row', alignItems: 'center', gap: 4 },
  addText: { color: colors.primary, fontWeight: '700', fontSize: typography.small },
  fieldHint: {
    fontSize: typography.tiny,
    color: colors.textLight,
  },
  card: {
    backgroundColor: colors.surface,
    borderRadius: radius.md,
    padding: spacing.md,
    gap: spacing.sm,
  },
  itemLine: { flexDirection: 'row', justifyContent: 'space-between' },
  itemName: { flex: 1, paddingRight: spacing.sm, color: colors.ink, fontSize: typography.body },
  itemValue: { color: colors.ink, fontWeight: '600', fontSize: typography.body },
  divider: { height: 1, backgroundColor: colors.border, marginVertical: spacing.xs },
  line: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center' },
  lineLabel: { color: colors.muted, fontSize: typography.body },
  lineValue: { color: colors.ink, fontWeight: '700', fontSize: typography.body },
  warnValue: { color: colors.warning, fontWeight: '800', fontSize: typography.body },
  successValue: { color: colors.success, fontWeight: '800', fontSize: typography.body },
  totalLabel: { fontSize: typography.h3, fontWeight: '800', color: colors.ink },
  total: { fontSize: typography.h2, fontWeight: '800', color: colors.primaryDark },
  payCard: {
    backgroundColor: colors.surface,
    borderRadius: radius.md,
    padding: spacing.md,
    borderWidth: 1,
    borderColor: colors.border,
    gap: spacing.sm,
  },
  payHead: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
  },
  payIndex: { fontSize: typography.small, fontWeight: '800', color: colors.muted },
  hint: { fontSize: typography.tiny, color: colors.textLight, lineHeight: 16 },
  footer: { padding: spacing.lg },
});
