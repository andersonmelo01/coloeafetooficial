import React from 'react';
import { Image, Pressable, StyleSheet, Text, View } from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { useCart } from '../contexts/CartContext';
import { Produto } from '../types';
import { colors, radius, typography } from '../theme';

interface Props {
  produto: Produto;
}

export const ProductCard: React.FC<Props> = ({ produto }) => {
  const { add } = useCart();

  const handlePress = () => {
    add(produto, 1);
  };

  const semEstoque = !!produto.controle_estoque && produto.estoque <= 0;

  return (
    <Pressable
      onPress={handlePress}
      disabled={semEstoque}
      style={({ pressed }) => [
        styles.card,
        semEstoque && styles.cardOff,
        pressed && !semEstoque && styles.cardPressed,
      ]}
    >
      <View style={styles.imgWrap}>
        {produto.imagem ? (
          <Image source={{ uri: produto.imagem }} style={styles.img} resizeMode="cover" />
        ) : (
          <View style={styles.imgEmpty}>
            <Ionicons name="shirt-outline" size={26} color={colors.textLight} />
          </View>
        )}
        {!!produto.promocional && (
          <View style={styles.tagPromo}>
            <Text style={styles.tagPromoText}>PROMO</Text>
          </View>
        )}
        {!!semEstoque && (
          <View style={styles.tagOff}>
            <Text style={styles.tagOffText}>SEM ESTOQUE</Text>
          </View>
        )}
      </View>

      <View style={styles.body}>
        <Text style={styles.nome} numberOfLines={2}>
          {produto.nome}
        </Text>
        {!!produto.preco_original_formatado && produto.promocional && (
          <Text style={styles.precoOld}>{produto.preco_original_formatado}</Text>
        )}
        <Text style={styles.preco}>{produto.preco_formatado}</Text>
      </View>

      <View style={styles.footer}>
        <View style={styles.addBtn}>
          <Ionicons name="add" size={16} color="#fff" />
          <Text style={styles.addText}>Adicionar</Text>
        </View>
      </View>
    </Pressable>
  );
};

const styles = StyleSheet.create({
  card: {
    flex: 1,
    backgroundColor: colors.surface,
    borderRadius: radius.md,
    borderWidth: 1,
    borderColor: colors.border,
    overflow: 'hidden',
    marginBottom: 12,
  },
  cardPressed: {
    transform: [{ scale: 0.985 }],
    borderColor: colors.primary,
  },
  cardOff: {
    opacity: 0.45,
  },
  imgWrap: {
    height: 100,
  },
  img: {
    width: '100%',
    height: '100%',
  },
  imgEmpty: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: colors.primaryLight,
  },
  tagPromo: {
    position: 'absolute',
    top: 8,
    left: 8,
    backgroundColor: colors.danger,
    borderRadius: radius.sm,
    paddingHorizontal: 7,
    paddingVertical: 3,
  },
  tagPromoText: {
    color: '#fff',
    fontSize: typography.tiny,
    fontWeight: '800',
  },
  tagOff: {
    position: 'absolute',
    top: 8,
    right: 8,
    backgroundColor: 'rgba(44,22,32,0.75)',
    borderRadius: radius.sm,
    paddingHorizontal: 7,
    paddingVertical: 3,
  },
  tagOffText: {
    color: '#fff',
    fontSize: typography.tiny,
    fontWeight: '700',
  },
  body: {
    paddingHorizontal: 10,
    paddingTop: 8,
    paddingBottom: 2,
  },
  nome: {
    fontSize: typography.small,
    color: colors.ink,
    minHeight: 34,
    fontWeight: '600',
  },
  precoOld: {
    fontSize: typography.tiny,
    color: colors.textLight,
    textDecorationLine: 'line-through',
    marginTop: 3,
  },
  preco: {
    fontSize: typography.h3,
    color: colors.primaryDark,
    fontWeight: '800',
    marginTop: 2,
  },
  footer: {
    paddingHorizontal: 10,
    paddingBottom: 10,
  },
  addBtn: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 6,
    backgroundColor: colors.primary,
    borderRadius: radius.full,
    paddingVertical: 9,
  },
  addText: {
    color: '#fff',
    fontSize: typography.small,
    fontWeight: '700',
  },
});
