import React from 'react';
import { ActivityIndicator, StyleSheet, Text, View } from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { useAuth } from '../contexts/AuthContext';
import { colors, radius, shadow } from '../theme';

/**
 * Tela mostrada enquanto o app restaura a sessão do usuário
 * (token salvo + dados de /me.php). Se a restauração falhar,
 * o app navega para o Login.
 */
export const BootstrapScreen: React.FC = () => {
  const { loading } = useAuth();

  return (
    <View style={styles.center}>
      <View style={styles.logoWrap}>
        <Ionicons name="heart-circle" size={72} color={colors.primary} />
      </View>
      <Text style={styles.title}>ColoAfeto</Text>
      <Text style={styles.subtitle}>PDV — Restaurando sessão...</Text>
      {loading ? (
        <ActivityIndicator size="small" color={colors.primary} style={styles.loader} />
      ) : null}
    </View>
  );
};

const styles = StyleSheet.create({
  center: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: colors.cream,
  },
  logoWrap: {
    width: 96,
    height: 96,
    borderRadius: radius.xl,
    backgroundColor: colors.surface,
    alignItems: 'center',
    justifyContent: 'center',
    ...shadow.float,
  },
  title: {
    marginTop: 20,
    fontSize: 28,
    fontWeight: '800',
    color: colors.primaryDark,
  },
  subtitle: {
    marginTop: 4,
    fontSize: 14,
    color: colors.textLight,
  },
  loader: {
    marginTop: 16,
  },
});
