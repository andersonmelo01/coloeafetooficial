import React from 'react';
import { FlatList, Pressable, StyleSheet, Text, View } from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { useNavigation } from '@react-navigation/native';
import { NativeStackNavigationProp } from '@react-navigation/native-stack';
import { Screen } from '../components/Screen';
import { AppButton } from '../components/AppButton';
import { useCart } from '../contexts/CartContext';
import { useAuth } from '../contexts/AuthContext';
import { colors, shadow, spacing, typography } from '../theme';
import { money } from '../utils/format';
import { RootStackParamList } from '../navigation';

export const CartScreen: React.FC = () => {
  const navigation = useNavigation<NativeStackNavigationProp<RootStackParamList>>();
  const { items, subtotal, setQuantity, remove } = useCart();

  return (
    <Screen>
      <FlatList
        data={items}
        keyExtractor={(i) => String(i.produto_id)}
        contentContainerStyle={styles.list}
        ListEmptyComponent={<Text style={styles.empty}>Seu carrinho está vazio</Text>}
        renderItem={({ item }) => (
          <View style={styles.row}>
            {!!item.imagem && <Ionicons name="fish-outline" size={20} color={colors.primary} />}
            <View style={styles.info}>
              <Text style={styles.nome}>{item.nome}</Text>
              <Text style={styles.preco}>{money(item.preco * item.quantidade)}</Text>
            </View>
            <View style={styles.stepper}>
              <Pressable style={styles.stepBtn} onPress={() => setQuantity(item.produto_id, item.quantidade - 1)}>
                <Ionicons name="remove" size={16} color={colors.primary} />
              </Pressable>
              <Text style={styles.qty}>{item.quantidade}</Text>
              <Pressable style={styles.stepBtn} onPress={() => setQuantity(item.produto_id, item.quantidade + 1)}>
                <Ionicons name="add" size={16} color={colors.primary} />
              </Pressable>
            </View>
            <Pressable onPress={() => remove(item.produto_id)} hitSlop={10}>
              <Ionicons name="trash-outline" size={18} color={colors.danger} />
            </Pressable>
          </View>
        )}
      />
      <View style={styles.footer}>
        <View style={styles.totalRow}>
          <Text style={styles.totalLabel}>Subtotal</Text>
          <Text style={styles.total}>{money(subtotal)}</Text>
        </View>
        <AppButton title="Continuar" onPress={() => navigation.navigate('Checkout')} disabled={items.length === 0} />
      </View>
    </Screen>
  );
};

const styles = StyleSheet.create({
  list: { padding: spacing.lg, gap: spacing.md },
  empty: { textAlign: 'center', color: colors.muted, marginTop: spacing.xl, fontSize: typography.body },
  row: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: spacing.md,
    backgroundColor: colors.surface,
    borderRadius: 14,
    padding: spacing.md,
    ...shadow.card,
  },
  info: { flex: 1 },
  nome: { fontSize: typography.body, fontWeight: '700', color: colors.ink },
  preco: { fontSize: typography.body, color: colors.primary, fontWeight: '700', marginTop: 2 },
  stepper: { flexDirection: 'row', alignItems: 'center', gap: spacing.sm },
  stepBtn: {
    width: 26,
    height: 26,
    borderRadius: 999,
    borderWidth: 1,
    borderColor: colors.border,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: colors.surface,
  },
  qty: { minWidth: 22, textAlign: 'center', fontWeight: '700', fontSize: typography.body },
  footer: {
    padding: spacing.lg,
    borderTopWidth: 1,
    borderTopColor: colors.border,
    gap: spacing.md,
  },
  totalRow: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center' },
  totalLabel: { color: colors.muted, fontSize: typography.body },
  total: { fontSize: typography.h2, fontWeight: '800', color: colors.ink },
});
