import React, { createContext, useContext, useMemo, useState } from 'react';
import { CartItem, Produto } from '../types';

interface CartContextData {
  items: CartItem[];
  count: number;
  subtotal: number;
  add: (produto: Produto, quantidade: number) => void;
  setQuantity: (produtoId: number, quantidade: number) => void;
  remove: (produtoId: number) => void;
  clear: () => void;
}

const CartContext = createContext<CartContextData>({} as CartContextData);

export const CartProvider: React.FC<{ children: React.ReactNode }> = ({ children }) => {
  const [items, setItems] = useState<CartItem[]>([]);

  const add = (produto: Produto, quantidade: number) => {
    setItems((prev) => {
      const idx = prev.findIndex((i) => i.produto_id === produto.id);
      const qty = Math.max(1, quantidade);
      if (idx >= 0) {
        const next = [...prev];
        let novaQtd = next[idx].quantidade + qty;
        if (produto.controle_estoque && produto.estoque > 0) {
          novaQtd = Math.min(novaQtd, produto.estoque);
        }
        next[idx] = { ...next[idx], quantidade: novaQtd };
        return next;
      }
      return [
        ...prev,
        {
          produto_id: produto.id,
          nome: produto.nome,
          preco: produto.preco_atual,
          quantidade: qty,
          imagem: produto.imagem,
          estoque: produto.estoque,
          controle_estoque: produto.controle_estoque,
        },
      ];
    });
  };

  const setQuantity = (produtoId: number, quantidade: number) => {
    setItems((prev) =>
      prev.map((i) => {
        if (i.produto_id !== produtoId) return i;
        let qtd = Math.max(1, quantidade);
        if (i.controle_estoque && i.estoque > 0) {
          qtd = Math.min(qtd, i.estoque);
        }
        return { ...i, quantidade: qtd };
      })
    );
  };

  const remove = (produtoId: number) => {
    setItems((prev) => prev.filter((i) => i.produto_id !== produtoId));
  };

  const clear = () => setItems([]);

  const count = useMemo(() => items.reduce((s, i) => s + i.quantidade, 0), [items]);
  const subtotal = useMemo(
    () => items.reduce((s, i) => s + i.preco * i.quantidade, 0),
    [items]
  );

  const value = useMemo(
    () => ({ items, count, subtotal, add, setQuantity, remove, clear }),
    [items, count, subtotal, add, setQuantity, remove, clear]
  );

  return <CartContext.Provider value={value}>{children}</CartContext.Provider>;
};

export const useCart = (): CartContextData => useContext(CartContext);