import React, { useCallback, useState } from 'react';
import { FlatList, Pressable, StyleSheet, Text, View } from 'react-native';
import { useFocusEffect, useNavigation } from '@react-navigation/native';
import { NativeStackNavigationProp } from '@react-navigation/native-stack';
import { Screen } from '../components/Screen';
import { useAuth } from '../contexts/AuthContext';
import { getVendas } from '../api/endpoints';
import { colors, radius, shadow, spacing, typography } from '../theme';
import { money } from '../utils/format';
import { VendaLista } from '../types';
import { RootStackParamList } from '../navigation';

const STATUS_LABEL: Record<string, string> = {
  finalizada: 'Finalizada',
  pendente: 'Pendente',
  cancelada: 'Cancelada',
  aberta: 'Aberta',
};

const STATUS_COLOR: Record<string, string> = {
  finalizada: colors.success,
  pendente: colors.warning,
  cancelada: colors.danger,
  aberta: colors.info,
};

export const HistoricoScreen: React.FC = () => {
  const navigation = useNavigation<NativeStackNavigationProp<RootStackParamList>>();
  const { token } = useAuth();
  const [vendas, setVendas] = useState<VendaLista[]>([]);
  const [carregando, setCarregando] = useState(false);

  const carregar = useCallback(async () => {
    if (!token) return;
    setCarregando(true);
    try {
      const r = await getVendas(token, {});
      setVendas(r.vendas);
    } catch {
      // silently keep list
    } finally {
      setCarregando(false);
    }
  }, [token]);

  useFocusEffect(
    useCallback(() => {
      carregar();
    }, [carregar])
  );

  return (
    <Screen padded={false}>
      <Text style={styles.title}>Vendas recentes</Text>
      <FlatList
        data={vendas}
        keyExtractor={(v) => String(v.id)}
        contentContainerStyle={styles.list}
        onRefresh={carregar}
        refreshing={carregando}
        ListEmptyComponent={
          <Text style={styles.empty}>{carregando ? 'Carregando...' : 'Nenhuma venda ainda'}</Text>
        }
        renderItem={({ item }) => (
          <Pressable
            style={styles.row}
            onPress={() => navigation.navigate('VendaDetalhe', { vendaId: item.id })}
          >
            <View style={styles.info}>
              <Text style={styles.numero}>#{item.numero}</Text>
              <Text style={styles.cliente}>{item.cliente || 'Sem cliente'}</Text>
              <Text style={styles.data}>{item.data}</Text>
            </View>
            <View style={styles.right}>
              <Text style={styles.total}>{money(item.total)}</Text>
              <View style={[styles.badge, { backgroundColor: STATUS_COLOR[item.status] ?? colors.muted }]}>
                <Text style={styles.badgeText}>{STATUS_LABEL[item.status] ?? item.status}</Text>
              </View>
            </View>
          </Pressable>
        )}
      />
    </Screen>
  );
};

const styles = StyleSheet.create({
  title: {
    fontSize: typography.h1,
    fontWeight: '800',
    color: colors.ink,
    paddingHorizontal: spacing.lg,
    paddingTop: spacing.md,
    paddingBottom: spacing.sm,
  },
  list: { padding: spacing.lg, gap: spacing.md },
  empty: { textAlign: 'center', color: colors.muted, marginTop: spacing.xl, fontSize: typography.body },
  row: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    backgroundColor: colors.surface,
    borderRadius: radius.md,
    padding: spacing.md,
    ...shadow.card,
  },
  info: { flex: 1 },
  numero: { fontSize: typography.h3, fontWeight: '800', color: colors.ink },
  cliente: { fontSize: typography.small, color: colors.muted, marginTop: 2 },
  data: { fontSize: typography.tiny, color: colors.textLight, marginTop: 2 },
  right: { alignItems: 'flex-end', gap: spacing.xs },
  total: { fontSize: typography.h3, fontWeight: '800', color: colors.primaryDark },
  badge: {
    paddingHorizontal: spacing.sm,
    paddingVertical: 3,
    borderRadius: radius.full,
  },
  badgeText: { color: colors.surface, fontSize: typography.tiny, fontWeight: '700' },
});
