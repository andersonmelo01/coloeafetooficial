import React, { useEffect, useMemo, useState } from 'react';
import { Alert, KeyboardAvoidingView, Platform, ScrollView, StyleSheet, Text, View } from 'react-native';
import { useNavigation } from '@react-navigation/native';
import { NativeStackNavigationProp } from '@react-navigation/native-stack';
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
import { money } from '../utils/format';
import { Cliente, MetodoPagamento, PagamentoEnvio, VendaEnvio } from '../types';
import { RootStackParamList } from '../navigation';

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

  const [metodoId, setMetodoId] = useState<string>('pix');
  const [metodoModal, setMetodoModal] = useState(false);
  const [desconto, setDesconto] = useState(0);

  const [parcelas, setParcelas] = useState(1);
  const [parcelaModal, setParcelaModal] = useState(false);
  const [vencimento, setVencimento] = useState('');
  const [emailRecibo, setEmailRecibo] = useState('');
  const [troco, setTroco] = useState(0);

  const ehPrazo = metodoId === 'a_prazo';
  const ehDinheiro = metodoId === 'dinheiro';
  const total = Math.max(0, subtotal - desconto);

  useEffect(() => {
    if (!token) return;
    getClientes(token, buscaCliente)
      .then((r) => setClientes(r.clientes))
      .catch(() => {});
  }, [token, buscaCliente]);

  const confirmar = async () => {
    if (!token) return;
    if (items.length === 0) {
      show('Carrinho vazio', 'error');
      return;
    }
    const pagamentos: PagamentoEnvio[] = [
      {
        metodo: metodoId as MetodoPagamento,
        valor: total,
        parcelas: ehPrazo ? parcelas : 1,
        ...(ehPrazo && vencimento ? { vencimento } : {}),
      },
    ];
    const body: VendaEnvio = {
      itens: items.map((i) => ({ produto_id: i.produto_id, quantidade: i.quantidade })),
      pagamentos,
      ...(desconto > 0 ? { desconto } : {}),
      ...(clienteId ? { cliente_id: clienteId } : {}),
      ...(emailRecibo ? { email_recibo: emailRecibo } : {}),
    };
    try {
      const r = await createVenda(token, body);
      clear();
      navigation.navigate('Result', { vendaId: r.venda.id });
    } catch (e) {
      show((e as Error).message ?? 'Erro ao finalizar venda', 'error');
    }
  };

  return (
    <Screen>
      <KeyboardAvoidingView
        style={styles.flex}
        behavior={Platform.OS === 'ios' ? 'padding' : undefined}
      >
        <ScrollView contentContainerStyle={styles.content}>
          <Text style={styles.title}>Pagamento</Text>

          <AppButton
            title={clienteId ? `Cliente selecionado` : 'Identificar cliente'}
            variant="outline"
            onPress={() => setClienteModal(true)}
            icon="person"
            small
          />
          <Text style={styles.fieldHint}>Opcional — usado para venda a prazo/fiado</Text>

          <Text style={styles.section}>Pagamento</Text>
          <AppButton
            title={metodoId === 'pix' ? 'PIX' : metodoId === 'dinheiro' ? 'Dinheiro' : 'Cartão / A prazo'}
            onPress={() => setMetodoModal(true)}
            icon="card"
            small
          />

          {metodoId === 'a_prazo' && (
            <View>
              <AppButton
                title={`${parcelas}x de ${money(total / parcelas)}`}
                variant="outline"
                onPress={() => setParcelaModal(true)}
                small
              />
              <AppInput
                label="Vencimento (primeira parcela)"
                value={vencimento}
                onChangeText={setVencimento}
                placeholder="dd/mm/aaaa"
              />
            </View>
          )}

          {metodoId === 'dinheiro' && (
            <AppInput
              label="Valor recebido"
              value={String(troco)}
              onChangeText={(t) => setTroco(Number(t))}
              keyboardType="decimal-pad"
              placeholder="0,00"
              right={<Text style={styles.rico}>R$</Text>}
            />
          )}

          <AppInput
            label="E-mail para o recibo (opcional)"
            value={emailRecibo}
            onChangeText={setEmailRecibo}
            placeholder="cliente@email.com"
            keyboardType="email-address"
            autoCapitalize="none"
          />

          <View style={styles.totalRow}>
            <Text style={styles.totalLabel}>Total</Text>
            <Text style={styles.total}>{money(total)}</Text>
          </View>
        </ScrollView>

        <View style={styles.footer}>
          <AppButton title="Confirmar venda" onPress={confirmar} icon="checkmark-circle" />
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
          visible={metodoModal}
          title="Forma de pagamento"
          options={metodos.map((m, i) => ({ id: i, label: m.label, metodo: m.id }))}
          selectedId={metodos.findIndex((m) => m.id === metodoId)}
          onSelect={(o) => {
            setMetodoId(String(o.id));
            setMetodoModal(false);
          }}
          onClose={() => setMetodoModal(false)}
        />
        <SelectModal
          visible={parcelaModal}
          title="Parcelas a prazo"
          options={[1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12].map((n) => ({
            id: n,
            label: `${n}x de ${money(total / n)}`,
          }))}
          selectedId={parcelas}
          onSelect={(o) => {
            setParcelas(o.id);
            setParcelaModal(false);
          }}
          onClose={() => setParcelaModal(false)}
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
    fontWeight: '700',
    color: colors.muted,
    marginTop: spacing.sm,
  },
  fieldHint: {
    fontSize: typography.tiny,
    color: colors.textLight,
  },
  rico: {
    fontSize: typography.body,
    fontWeight: '700',
    color: colors.textLight,
  },
  totalRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginTop: spacing.sm,
  },
  totalLabel: { fontSize: typography.body, color: colors.muted },
  total: { fontSize: typography.h2, fontWeight: '800', color: colors.ink },
  footer: { padding: spacing.lg },
});
