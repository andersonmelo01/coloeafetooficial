import React, { useCallback, useState } from 'react';
import { Alert, Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';
import { useFocusEffect, useNavigation, useRoute, RouteProp } from '@react-navigation/native';
import { NativeStackNavigationProp } from '@react-navigation/native-stack';
import { Ionicons } from '@expo/vector-icons';
import { Screen } from '../components/Screen';
import { AppButton } from '../components/AppButton';
import { useAuth } from '../contexts/AuthContext';
import { useToast } from '../contexts/ToastContext';
import { getVenda, cancelarVenda } from '../api/endpoints';
import { colors, radius, spacing, typography } from '../theme';
import { VendaDetalheResponse } from '../types';
import { RootStackParamList } from '../navigation';

type DetalheRouteProp = RouteProp<RootStackParamList, 'VendaDetalhe'>;

export const VendaDetalheScreen: React.FC = () => {
  const navigation = useNavigation<NativeStackNavigationProp<RootStackParamList>>();
  const route = useRoute<DetalheRouteProp>();
  const { token } = useAuth();
  const { show } = useToast();
  const [data, setData] = useState<VendaDetalheResponse | null>(null);
  const [carregando, setCarregando] = useState(true);

  const carregar = useCallback(async () => {
    if (!token) return;
    setCarregando(true);
    try {
      setData(await getVenda(token, route.params.vendaId));
    } catch (e) {
      show((e as Error).message, 'error');
    } finally {
      setCarregando(false);
    }
  }, [token, route.params.vendaId]);

  useFocusEffect(
    useCallback(() => {
      carregar();
    }, [carregar])
  );

  const cancelar = () => {
    if (!token || !data) return;
    Alert.alert('Cancelar venda', 'Deseja cancelar esta venda e estornar?', [
      { text: 'Não', style: 'cancel' },
      {
        text: 'Cancelar venda',
        style: 'destructive',
        onPress: async () => {
          try {
            const r = await cancelarVenda(token, route.params.vendaId, 'Cancelada no PDV');
            show(r.message, 'success');
            navigation.goBack();
          } catch (e) {
            show((e as Error).message, 'error');
          }
        },
      },
    ]);
  };

  if (!data) {
    return (
      <Screen>
        <Text style={styles.loading}>{carregando ? 'Carregando...' : 'Venda não encontrada'}</Text>
      </Screen>
    );
  }

  const { venda, itens, pagamentos, parcelas } = data;

  return (
    <Screen>
      <ScrollView contentContainerStyle={styles.content}>
        <View style={styles.header}>
          <Text style={styles.numero}>Venda #{venda.numero}</Text>
          <Text style={styles.status}>{venda.status.toUpperCase()}</Text>
          <Text style={styles.meta}>{venda.finalizada_em}</Text>
          <Text style={styles.meta}>Vendedor: {venda.vendedor}</Text>
          {!!venda.cliente && <Text style={styles.meta}>Cliente: {venda.cliente}</Text>}
        </View>

        <Text style={styles.section}>Itens</Text>
        {itens.map((it, idx) => (
          <View key={String(it.produto_id ?? idx)} style={styles.line}>
            <Text style={styles.lineName}>
              {it.quantidade}x {it.nome}
            </Text>
            <Text style={styles.lineValue}>R$ {it.total.toFixed(2)}</Text>
          </View>
        ))}

        <Text style={styles.section}>Pagamentos</Text>
        {pagamentos.map((p) => (
          <View key={p.id} style={styles.line}>
            <Text style={styles.lineName}>{p.metodo_label}</Text>
            <Text style={styles.lineValue}>{p.valor_formatado}</Text>
          </View>
        ))}

        {parcelas.length > 0 && (
          <>
            <Text style={styles.section}>Parcelas a prazo</Text>
            {parcelas.map((p) => (
              <View key={p.id} style={styles.line}>
                <Text style={styles.lineName}>
                  {p.parcela}x — vence {p.vencimento}
                </Text>
                <Text style={styles.lineValue}>{p.valor_formatado}</Text>
              </View>
            ))}
          </>
        )}

        <View style={styles.totais}>
          <View style={styles.line}>
            <Text style={styles.lineName}>Subtotal</Text>
            <Text style={styles.lineValue}>{venda.subtotal_formatado}</Text>
          </View>
          <View style={styles.line}>
            <Text style={styles.lineName}>Desconto</Text>
            <Text style={styles.lineValue}>{venda.desconto_formatado}</Text>
          </View>
          <View style={styles.line}>
            <Text style={styles.totalLabel}>Total</Text>
            <Text style={styles.total}>{venda.total_formatado}</Text>
          </View>
        </View>

        <AppButton
          title="Abrir cupom"
          icon="receipt"
          variant="outline"
          onPress={() => navigation.navigate('Cupom', { vendaId: route.params.vendaId })}
        />

        {venda.status !== 'cancelada' && (
          <Pressable style={styles.cancelBtn} onPress={cancelar}>
            <Ionicons name="close-circle-outline" size={18} color={colors.danger} />
            <Text style={styles.cancelText}>Cancelar venda</Text>
          </Pressable>
        )}
      </ScrollView>
    </Screen>
  );
};

const styles = StyleSheet.create({
  content: { padding: spacing.lg, gap: spacing.sm },
  loading: { textAlign: 'center', color: colors.muted, marginTop: spacing.xl },
  header: {
    backgroundColor: colors.surface,
    borderRadius: radius.md,
    padding: spacing.lg,
    gap: 2,
    marginBottom: spacing.md,
  },
  numero: { fontSize: typography.h1, fontWeight: '800', color: colors.ink },
  status: { fontSize: typography.small, fontWeight: '800', color: colors.primary, marginTop: 2 },
  meta: { fontSize: typography.small, color: colors.muted },
  section: {
    fontSize: typography.small,
    fontWeight: '800',
    color: colors.muted,
    marginTop: spacing.md,
    marginBottom: spacing.xs,
  },
  line: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    paddingVertical: 4,
  },
  lineName: { fontSize: typography.body, color: colors.ink, flex: 1, paddingRight: spacing.sm },
  lineValue: { fontSize: typography.body, color: colors.ink, fontWeight: '600' },
  totais: {
    marginTop: spacing.md,
    borderTopWidth: 1,
    borderTopColor: colors.border,
    paddingTop: spacing.md,
  },
  totalLabel: { fontSize: typography.h3, fontWeight: '800', color: colors.ink },
  total: { fontSize: typography.h3, fontWeight: '800', color: colors.primaryDark },
  cancelBtn: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: spacing.sm,
    marginTop: spacing.xl,
    paddingVertical: spacing.md,
    borderRadius: radius.md,
    borderWidth: 1,
    borderColor: colors.danger,
  },
  cancelText: { color: colors.danger, fontWeight: '700', fontSize: typography.body },
});
