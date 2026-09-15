import React from 'react';
import {
  ActivityIndicator,
  Pressable,
  StyleSheet,
  Text,
  TextStyle,
  ViewStyle,
} from 'react-native';
import { colors, radius, typography } from '../theme';

type Variant = 'primary' | 'outline' | 'danger' | 'ghost' | 'success' | 'dark';

interface Props {
  title: string;
  onPress?: () => void;
  variant?: Variant;
  disabled?: boolean;
  loading?: boolean;
  icon?: React.ReactNode;
  small?: boolean;
  style?: ViewStyle;
  textStyle?: TextStyle;
}

export const AppButton: React.FC<Props> = ({
  title,
  onPress,
  variant = 'primary',
  disabled,
  loading,
  icon,
  small,
  style,
  textStyle,
}) => {
  return (
    <Pressable
      onPress={onPress}
      disabled={disabled || loading}
      style={({ pressed }) => [
        styles.base,
        variantStyles[variant],
        small && styles.small,
        (disabled || loading) && styles.disabled,
        pressed && !disabled && styles.pressed,
        style,
      ]}
    >
      {loading ? (
        <ActivityIndicator color={variant === 'outline' || variant === 'ghost' ? colors.primary : '#fff'} />
      ) : (
        <React.Fragment>
          {icon}
          <Text style={[{ color: labelColor[variant] }, small && styles.smallText, textStyle]}>
            {title}
          </Text>
        </React.Fragment>
      )}
    </Pressable>
  );
};

const styles = StyleSheet.create({
  base: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    borderRadius: radius.full,
    paddingVertical: 15,
    paddingHorizontal: 22,
    minHeight: 52,
  },
  small: {
    minHeight: 40,
    paddingVertical: 10,
    paddingHorizontal: 16,
  },
  smallText: {
    fontSize: typography.small,
  },
  disabled: {
    opacity: 0.55,
  },
  pressed: {
    transform: [{ scale: 0.98 }],
    opacity: 0.9,
  },
});

const variantStyles: Record<Variant, ViewStyle> = {
  primary: {
    backgroundColor: colors.primary,
    shadowColor: colors.primary,
    shadowOffset: { width: 0, height: 6 },
    shadowOpacity: 0.35,
    shadowRadius: 12,
    elevation: 5,
  },
  outline: {
    backgroundColor: 'transparent',
    borderWidth: 2,
    borderColor: colors.primary,
  },
  danger: {
    backgroundColor: colors.danger,
  },
  ghost: {
    backgroundColor: colors.primaryLight,
  },
  success: {
    backgroundColor: colors.success,
  },
  dark: {
    backgroundColor: colors.ink,
  },
};

const labelColor: Record<Variant, string> = {
  primary: '#fff',
  outline: colors.primary,
  danger: '#fff',
  ghost: colors.primaryDark,
  success: '#fff',
  dark: '#fff',
};