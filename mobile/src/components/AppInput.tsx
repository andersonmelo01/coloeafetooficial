import React from 'react';
import { KeyboardTypeOptions, StyleSheet, Text, TextInput, View, ViewStyle } from 'react-native';
import { colors, radius, typography } from '../theme';

interface Props {
  label?: string;
  value: string;
  onChangeText: (t: string) => void;
  placeholder?: string;
  keyboardType?: KeyboardTypeOptions;
  secure?: boolean;
  multiline?: boolean;
  autoCapitalize?: 'none' | 'sentences' | 'words' | 'characters';
  right?: React.ReactNode;
  style?: ViewStyle;
  error?: string;
  hint?: string;
  editable?: boolean;
}

export const AppInput: React.FC<Props> = ({
  label,
  value,
  onChangeText,
  placeholder,
  keyboardType,
  secure,
  multiline,
  autoCapitalize = 'sentences',
  right,
  style,
  error,
  hint,
  editable = true,
}) => {
  return (
    <View style={[styles.wrap, style]}>
      {!!label && <Text style={styles.label}>{label}</Text>}
      <View style={[styles.box, !!error && styles.boxError, !editable && styles.boxDisabled]}>
        <TextInput
          value={value}
          onChangeText={onChangeText}
          placeholder={placeholder}
          placeholderTextColor={colors.textLight}
          keyboardType={keyboardType}
          secureTextEntry={secure}
          multiline={multiline}
          autoCapitalize={autoCapitalize}
          style={[styles.input, multiline && styles.multiline]}
          editable={editable}
        />
        {right}
      </View>
      {!!error ? (
        <Text style={styles.errorText}>{error}</Text>
      ) : !!hint ? (
        <Text style={styles.hint}>{hint}</Text>
      ) : null}
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
  boxError: {
    borderColor: colors.danger,
  },
  boxDisabled: {
    backgroundColor: '#F6EEEA',
  },
  input: {
    flex: 1,
    minHeight: 50,
    fontSize: typography.body,
    color: colors.ink,
    paddingVertical: 12,
  },
  multiline: {
    minHeight: 84,
    textAlignVertical: 'top',
  },
  errorText: {
    marginTop: 5,
    fontSize: typography.tiny + 1,
    color: colors.danger,
  },
  hint: {
    marginTop: 5,
    fontSize: typography.tiny + 1,
    color: colors.textLight,
  },
});