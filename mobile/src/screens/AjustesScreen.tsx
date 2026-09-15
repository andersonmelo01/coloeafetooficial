import React, { useEffect, useState } from 'react';
import { Alert, ScrollView, StyleSheet, Text, View } from 'react-native';
import { Screen } from '../components/Screen';
import { AppButton } from '../components/AppButton';
import { AppInput } from '../components/AppInput';
import { useAuth } from '../contexts/AuthContext';
import { useToast } from '../contexts/ToastContext';
import { colors, radius, spacing, typography } from '../theme';

export const AjustesScreen: React.FC = () => {
  const { apiUrl, usuario, config, setServerUrl, signOut } = useAuth();
  const { show } = useToast();
  const [url, setUrl] = useState(apiUrl);
  const [salvando, setSalvando] = useState(false);

  useEffect(() => {
    setUrl(apiUrl);
  }, [apiUrl]);

  const salvar = async () => {
    if (!url.trim()) {
      show('Informe a URL do servidor', 'warning');
      return;
    }
    setSalvando(true);
    try {
      await setServerUrl(url.trim());
      show('Endereço salvo. Faça login novamente.', 'success');
    } catch (e) {
      show((e as Error).message, 'error');
    } finally {
      setSalvando(false);
    }
  };

  const sair = () => {
    Alert.alert('Sair', 'Deseja encerrar a sessão?', [
      { text: 'Não', style: 'cancel' },
      { text: 'Sair', style: 'destructive', onPress: () => signOut() },
    ]);
  };

  return (
    <Screen>
      <ScrollView contentContainerStyle={styles.content}>
        <View style={styles.card}>
          <Text style={styles.cardTitle}>Conta</Text>
          <Text style={styles.line}>
            <Text style={styles.lineLabel}>Usuário: </Text>
            {usuario?.nome ?? '-'}
          </Text>
          <Text style={styles.line}>
            <Text style={styles.lineLabel}>E-mail: </Text>
            {usuario?.email ?? '-'}
          </Text>
          {config && (
            <Text style={styles.line}>
              <Text style={styles.lineLabel}>Estabelecimento: </Text>
              {config.nome_estabelecimento}
            </Text>
          )}
        </View>

        <View style={styles.card}>
          <Text style={styles.cardTitle}>Servidor (API)</Text>
          <AppInput
            label="Endereço do PDV"
            value={url}
            onChangeText={setUrl}
            placeholder="https://coloeafetooficial.com.br/api/pdv"
            autoCapitalize="none"
            keyboardType="url"
          />
          <AppButton
            title={salvando ? 'Salvando...' : 'Salvar endereço'}
            onPress={salvar}
            disabled={salvando}
            small
          />
          <Text style={styles.hint}>
            Em produção use https://coloeafetooficial.com.br/api/pdv
          </Text>
        </View>

        <View style={styles.card}>
          <Text style={styles.cardTitle}>Sessão</Text>
          <AppButton title="Sair" variant="danger" onPress={sair} icon="log-out" />
        </View>

        <Text style={styles.version}>ColoAfeto PDV · v1.0.0</Text>
      </ScrollView>
    </Screen>
  );
};

const styles = StyleSheet.create({
  content: { padding: spacing.lg, gap: spacing.lg },
  card: {
    backgroundColor: colors.surface,
    borderRadius: radius.md,
    padding: spacing.lg,
    gap: spacing.xs,
  },
  cardTitle: {
    fontSize: typography.small,
    fontWeight: '800',
    color: colors.muted,
    marginBottom: spacing.xs,
    textTransform: 'uppercase',
  },
  line: { fontSize: typography.body, color: colors.ink },
  lineLabel: { color: colors.muted },
  hint: { fontSize: typography.tiny, color: colors.textLight, marginTop: spacing.xs },
  version: { textAlign: 'center', color: colors.textLight, fontSize: typography.tiny },
});
