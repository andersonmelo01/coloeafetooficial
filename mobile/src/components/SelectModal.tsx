import React, { useEffect, useMemo, useRef, useState } from 'react';
import {
  FlatList,
  ListRenderItem,
  Modal,
  Pressable,
  StyleSheet,
  Text,
  TextInput,
  View,
} from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { colors, radius, typography } from '../theme';

export interface Option<T> {
  id: number;
  label: string;
  value?: T;
}

interface Props<T> {
  visible: boolean;
  title: string;
  options: Option<T>[];
  selectedId: number | null;
  onSelect: (option: Option<T>) => void;
  onClose: () => void;
  searchable?: boolean;
  afterSelect?: () => void;
}

export function SelectModal<T>({
  visible,
  title,
  options,
  selectedId,
  onSelect,
  onClose,
  searchable,
  afterSelect,
}: Props<T>): React.ReactElement | null {
  const [q, setQ] = useState('');
  const inputRef = useRef<TextInput>(null);

  useEffect(() => {
    if (visible) {
      setQ('');
      const t = setTimeout(() => inputRef.current?.focus(), 280);
      return () => clearTimeout(t);
    }
  }, [visible]);

  const filtered = useMemo(() => {
    const term = q.trim().toLowerCase();
    if (!term) return options;
    return options.filter((o) => o.label.toLowerCase().includes(term));
  }, [options, q]);

  const handleSelect = (opt: Option<T>) => {
    onSelect(opt);
    afterSelect?.();
  };

  const renderItem: ListRenderItem<Option<T>> = ({ item }) => {
    const selected = item.id === selectedId && selectedId !== null;
    return (
      <Pressable
        onPress={() => handleSelect(item)}
        style={[styles.row, selected && styles.rowOn]}
      >
        <View style={[styles.check, selected && styles.checkOn]}>
          {selected && <Ionicons name="checkmark" size={13} color="#fff" />}
        </View>
        <Text style={[styles.label, selected && styles.labelOn]} numberOfLines={2}>
          {item.label}
        </Text>
      </Pressable>
    );
  };

  return (
    <Modal visible={visible} transparent animationType="fade" onRequestClose={onClose}>
      <Pressable style={styles.backdrop} onPress={onClose}>
        <Pressable style={styles.panel} onPress={(e) => e.stopPropagation()}>
          <View style={styles.header}>
            <Text style={styles.title}>{title}</Text>
            <Pressable onPress={onClose} hitSlop={10} style={styles.closeBtn}>
              <Ionicons name="close" size={24} color={colors.textLight} />
            </Pressable>
          </View>

          {!!searchable && (
            <View style={styles.searchBox}>
              <Ionicons name="search" size={16} color={colors.textLight} />
              <TextInput
                ref={inputRef}
                style={styles.searchInput}
                value={q}
                onChangeText={setQ}
                placeholder="Buscar..."
                placeholderTextColor={colors.textLight}
                autoCapitalize="none"
              />
            </View>
          )}

          <FlatList
            data={filtered}
            keyExtractor={(o) => String(o.id)}
            renderItem={renderItem}
            keyboardShouldPersistTaps="handled"
            style={styles.list}
            ListEmptyComponent={
              <Text style={styles.empty}>Nenhum item encontrado.</Text>
            }
          />
        </Pressable>
      </Pressable>
    </Modal>
  );
}

const styles = StyleSheet.create({
  backdrop: {
    flex: 1,
    backgroundColor: 'rgba(82, 25, 49, 0.45)',
    justifyContent: 'flex-end',
  },
  panel: {
    backgroundColor: colors.surface,
    borderTopLeftRadius: radius.xl,
    borderTopRightRadius: radius.xl,
    paddingTop: 18,
    paddingBottom: 28,
    maxHeight: '78%',
  },
  header: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingHorizontal: 18,
    paddingBottom: 12,
  },
  title: {
    fontSize: typography.h3,
    fontWeight: '800',
    color: colors.ink,
  },
  closeBtn: {
    padding: 4,
  },
  searchBox: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: colors.surface,
    borderRadius: radius.md,
    paddingHorizontal: 10,
    marginHorizontal: 16,
    marginBottom: 8,
  },
  searchInput: {
    flex: 1,
    paddingVertical: 9,
    paddingHorizontal: 8,
    fontSize: typography.body,
    color: colors.ink,
  },
  list: {
    flexGrow: 0,
  },
  row: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingVertical: 13,
    paddingHorizontal: 18,
    borderBottomWidth: StyleSheet.hairlineWidth,
    borderBottomColor: colors.border,
  },
  rowOn: {
    backgroundColor: colors.primaryLight,
  },
  check: {
    width: 20,
    height: 20,
    borderRadius: 10,
    borderWidth: 2,
    borderColor: colors.textLight,
    alignItems: 'center',
    justifyContent: 'center',
    marginRight: 12,
  },
  checkOn: {
    backgroundColor: colors.primary,
    borderColor: colors.primary,
  },
  label: {
    flex: 1,
    fontSize: typography.body,
    color: colors.ink,
  },
  labelOn: {
    fontWeight: '700',
  },
  empty: {
    textAlign: 'center',
    color: colors.textLight,
    paddingVertical: 22,
    fontSize: typography.small,
  },
});