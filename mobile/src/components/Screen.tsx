import React from 'react';
import { Platform, StyleSheet, Text, View, ViewStyle } from 'react-native';
import { EdgeInsets, useSafeAreaInsets } from 'react-native-safe-area-context';
import { colors } from '../theme';

interface Props {
  children: React.ReactNode;
  style?: ViewStyle;
  scroll?: boolean;
  padded?: boolean;
}

export const Screen: React.FC<Props> = ({ children, style, padded = true }) => {
  const insets = useSafeAreaInsets();
  return (
    <View style={[styles.base, { paddingTop: insets.top + (padded ? 8 : 0) }, style]}>
      {children}
    </View>
  );
};

const styles = StyleSheet.create({
  base: {
    flex: 1,
    backgroundColor: colors.cream,
  },
});