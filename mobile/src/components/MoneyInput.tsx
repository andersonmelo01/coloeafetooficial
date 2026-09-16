import React, { useEffect, useRef, useState } from 'react';
import { StyleSheet, Text, TextInput, View, ViewStyle } from 'react-native';
import { colors, radius, typography } from '../theme';
import { money, parseMoneyInput } from '../utils/format';

interface Props {
  label?: string;
  hint?: string;
  /** Valor atual em R$ (número). */
  value: number;
  /** Chamado sempre que o usuário digita, com o novo valor numérico. */
  onValueChange: (numberValue: number) => void;
  onChangeText?: (text: string) => void;
  placeholder?: string;
  style?: ViewStyle;
}

/** Campo de input com máscara de moeda brasileira (R$ 1.234,56). */
export const MoneyInput: React.FC<Props> = ({
  label,
  hint,
  value,
  onValueChange,
  onChangeText,
  placeholder,
  style,
}) => {
  const inputRef = useRef<TextInput>(null);
  const [text, setText] = useState(money(value));
  const [focused, setFocused] = useState(false);

  useEffect(() => {
    if (!focused) {
      setText(money(value));
    }
  }, [value, focused]);

  const handleChange = (raw: string) => {
    const digits = raw.replace(/[^\d]/g, '');
    if (digits.length === 0) {
      setText('0,00');
      onValueChange(0);
      if (onChangeText) onChangeText('');
      return;
    }
    const padded = digits.padStart(3, '0');
    const cents = padded.slice(-2);
    const reais = padded.slice(0, -2).replace(/^0+(?=\d)/, '') || '0';
    const formatted = `${Number(reais).toLocaleString('pt-BR')},${cents}`;
    setText(formatted);
    onValueChange(parseMoneyInput(formatted));
    if (onChangeText) onChangeText(formatted);
  };

  return (
    <View style={[styles.wrap, style]}>
      {!!label && <Text style={styles.label}>{label}</Text>}
      <View style={[styles.box, focused && styles.boxOn]}>
        <Text style={styles.prefix}>R$</Text>
        <TextInput
          ref={inputRef}
          value={text}
          onChangeText={handleChange}
          onFocus={() => setFocused(true)}
          onBlur={() => setFocused(false)}
          placeholder={placeholder ?? '0,00'}
          placeholderTextColor={colors.textLight}
          keyboardType="decimal-pad"
          style={styles.input}
        />
      </View>
      {!!hint && <Text style={styles.hint}>{hint}</Text>}
    </View>
  );
};

const styles = StyleSheet.create({
  wrap: {
    marginBottom: 14,
  },
  label: {
    fontSize: typography.small,
    fontWeight: '700',
    color: colors.muted,
    marginBottom: 6,
  },
  box: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: colors.surface,
    borderRadius: radius.md,
    borderWidth: 1.5,
    borderColor: colors.border,
    paddingHorizontal: 14,
  },
  boxOn: {
    borderColor: colors.primary,
  },
  prefix: {
    fontSize: typography.body,
    fontWeight: '700',
    color: colors.primaryDark,
    marginRight: 8,
  },
  input: {
    flex: 1,
    fontSize: typography.body,
    color: colors.ink,
    paddingVertical: 12,
    fontWeight: '600',
  },
  hint: {
    marginTop: 5,
    fontSize: typography.tiny,
    color: colors.textLight,
  },
});