import React, { useCallback, useEffect, useState } from 'react';
import { ActivityIndicator, FlatList, Pressable, StyleSheet, Text, TextInput, View } from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { useNavigation } from '@react-navigation/native';
import { Screen } from '../components/Screen';
import { ProductCard } from '../components/ProductCard';
import { useAuth } from '../contexts/AuthContext';
import { useCart } from '../contexts/CartContext';
import { getCategorias, getProdutos } from '../api/endpoints';
import { colors, radius, spacing, typography } from '../theme';
import { Categoria, Produto } from '../types';

export const PdvScreen: React.FC = () => {
  const navigation = useNavigation();
  const { token, usuario } = useAuth();
  const { count, subtotal } = useCart();

  const [produtos, setProdutos] = useState<Produto[]>([]);
  const [categorias, setCategorias] = useState<Categoria[]>([]);
  const [catAtiva, setCatAtiva] = useState<number | null>(null);
  const [busca, setBusca] = useState('');
  const [pagina, setPagina] = useState(1);
  const [totalPaginas, setTotalPaginas] = useState(1);
  const [carregando, setCarregando] = useState(false);

  const carregar = useCallback(
    async (page: number, q: string, cat: number | null, append = false) => {
      if (carregando) return;
      setCarregando(true);
      try {
        const r = await getProdutos(token, {
          q,
          categoria: cat ?? undefined,
          pagina: page,
          per_page: 30,
        });
        setProdutos((prev) => (append ? [...prev, ...r.produtos] : r.produtos));
        setTotalPaginas(r.pages);
        setPagina(r.pagina);
      } catch {
        // silencioso: mostra grid vazio em vez de quebrar o PDV
      } finally {
        setCarregando(false);
      }
    },
    [token]
  );

  useEffect(() => {
    carregar(1, busca, catAtiva === null ? null : catAtiva);
  }, [busca, catAtiva]);

  const aoFim = () => {
    if (pagina < totalPaginas) carregar(pagina + 1, busca, catAtiva, true);
  };

  useEffect(() => {
    getCategorias(token)
      .then((r) => setCategorias([{ id: 0, nome: 'Todos', total_produtos: 0 }, ...r.categorias]))
      .catch(() => {});
  }, [token]);

  return (
    <Screen>
      <View style={styles.top}>
        <View style={styles.titleRow}>
          <View>
            <Text style={styles.greeting}>Olá, {usuario?.nome?.split(' ')[0] ?? 'linda'}</Text>
            <Text style={styles.subtitle}>Escolha os itens para a venda</Text>
          </View>
          <Pressable style={styles.cartBtn} onPress={() => navigation.getParent()?.navigate('Cart' as never)}>
            <Ionicons name="cart" size={24} color={colors.surface} />
            {count > 0 && (
              <View style={styles.badge}>
                <Text style={styles.badgeText}>{count}</Text>
              </View>
            )}
          </Pressable>
        </View>
        <View style={styles.searchBox}>
          <Ionicons name="search" size={18} color={colors.muted} />
          <TextInput
            value={busca}
            onChangeText={setBusca}
            placeholder="Buscar produto, código ou SKU..."
            placeholderTextColor={colors.textLight}
            style={styles.searchInput}
          />
        </View>
        <FlatList
          horizontal
          showsHorizontalScrollIndicator={false}
          data={categorias}
          keyExtractor={(c) => String(c.id)}
          contentContainerStyle={styles.cats}
          renderItem={({ item }) => (
            <Pressable
              onPress={() => setCatAtiva(item.id === 0 ? null : item.id)}
              style={[styles.cat, catAtiva === item.id && styles.catOn]}
            >
              <Text style={[styles.catText, catAtiva === item.id && styles.catTextOn]}>
                {item.nome}
              </Text>
            </Pressable>
          )}
        />
      </View>

      <FlatList
        data={produtos}
        keyExtractor={(p) => String(p.id)}
        numColumns={2}
        columnWrapperStyle={styles.grid}
        contentContainerStyle={styles.list}
        showsVerticalScrollIndicator={false}
        onEndReached={aoFim}
        onEndReachedThreshold={0.4}
        ListFooterComponent={carregando ? <ActivityIndicator color={colors.primary} /> : null}
        renderItem={({ item }) => <ProductCard produto={item} />}
      />

      {count > 0 && (
        <Pressable style={styles.footer} onPress={() => navigation.getParent()?.navigate('Cart' as never)}>
          <View>
            <Text style={styles.footerCount}>{count} item(ns)</Text>
            <Text style={styles.footerSub}>Toque para ver o carrinho</Text>
          </View>
          <Text style={styles.footerTotal}>R$ {subtotal.toFixed(2)}</Text>
        </Pressable>
      )}
    </Screen>
  );
};

const styles = StyleSheet.create({
  top: {
    backgroundColor: colors.surface,
    borderBottomLeftRadius: radius.lg,
    borderBottomRightRadius: radius.lg,
    paddingHorizontal: spacing.lg,
    paddingBottom: spacing.md,
    elevation: 2,
    shadowColor: colors.ink,
    shadowOpacity: 0.05,
    shadowRadius: 6,
    shadowOffset: { width: 0, height: 2 },
  },
  titleRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginTop: spacing.sm,
  },
  greeting: {
    fontSize: typography.h2,
    fontWeight: '800',
    color: colors.ink,
  },
  subtitle: {
    fontSize: typography.small,
    color: colors.textLight,
    marginTop: 2,
  },
  cartBtn: {
    width: 46,
    height: 46,
    borderRadius: radius.full,
    backgroundColor: colors.primary,
    alignItems: 'center',
    justifyContent: 'center',
  },
  badge: {
    position: 'absolute',
    top: -4,
    right: -4,
    backgroundColor: colors.primaryDark,
    borderRadius: radius.full,
    minWidth: 20,
    height: 20,
    paddingHorizontal: 5,
    alignItems: 'center',
    justifyContent: 'center',
  },
  badgeText: {
    color: colors.surface,
    fontSize: typography.tiny,
    fontWeight: '800',
  },
  searchBox: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: colors.cream,
    borderRadius: radius.md,
    paddingHorizontal: spacing.md,
    marginTop: spacing.md,
    borderWidth: 1,
    borderColor: colors.border,
  },
  searchInput: {
    flex: 1,
    marginLeft: spacing.sm,
    paddingVertical: 10,
    fontSize: typography.body,
    color: colors.ink,
  },
  cats: {
    paddingTop: spacing.md,
    gap: spacing.sm,
  },
  cat: {
    paddingHorizontal: spacing.md,
    paddingVertical: 8,
    borderRadius: radius.full,
    backgroundColor: colors.cream,
    borderWidth: 1,
    borderColor: colors.border,
  },
  catOn: {
    backgroundColor: colors.primary,
    borderColor: colors.primary,
  },
  catText: {
    fontSize: typography.small,
    color: colors.muted,
    fontWeight: '600',
  },
  catTextOn: {
    color: colors.surface,
  },
  grid: {
    gap: spacing.md,
  },
  list: {
    padding: spacing.lg,
    gap: spacing.md,
    paddingBottom: 130,
  },
  footer: {
    position: 'absolute',
    left: spacing.lg,
    right: spacing.lg,
    bottom: spacing.lg,
    backgroundColor: colors.primaryDark,
    borderRadius: radius.lg,
    padding: spacing.lg,
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
  },
  footerCount: {
    color: colors.surface,
    fontWeight: '800',
    fontSize: typography.body,
  },
  footerSub: {
    color: colors.primaryLight,
    fontSize: typography.tiny,
    marginTop: 2,
  },
  footerTotal: {
    color: colors.surface,
    fontWeight: '800',
    fontSize: typography.h2,
  },
});
