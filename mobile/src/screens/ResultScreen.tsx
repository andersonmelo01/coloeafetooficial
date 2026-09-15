import React, { useState } from 'react';
import { Alert, StyleSheet, Text, View } from 'react-native';
import { useNavigation, useRoute, RouteProp } from '@react-navigation/native';
import { NativeStackNavigationProp } from '@react-navigation/native-stack';
import { Ionicons } from '@expo/vector-icons';
import { Screen } from '../components/Screen';
import { AppButton } from '../components/AppButton';
import { AppInput } from '../components/AppInput';
import { useAuth } from '../contexts/AuthContext';
import { useToast } from '../contexts/ToastContext';
import { enviarCupom } from '../api/endpoints';
import { colors, radius, spacing, typography } from '../theme';
import { RootStackParamList } from '../navigation';

type ResultRouteProp = RouteProp<RootStackParamList, 'Result'>;

export const ResultScreen: React.FC = () => {
  const navigation = useNavigation<NativeStackNavigationProp<RootStackParamList>>();
  const route = useRoute<ResultRouteProp>();
  const { token } = useAuth();
  const { show } = useToast();
  const [email, setEmail] = useState('');
  const [enviando, setEnviando] = useState(false);

  const { vendaId } = route.params;

  const handleCupom = async () => {
    if (!token || !email) return;
    setEnviando(true);
    try {
      const r = await enviarCupom(token, vendaId, email);
      show(r.message, 'success');
    } catch (e) {
      show((e as Error).message, 'error');
    } finally {
      setEnviando(false);
    }
  };

  return (
    <Screen>
      <View style={styles.center}>
        <View style={styles.iconWrap}>
          <Ionicons name="checkmark-circle" size={80} color={colors.success} />
        </View>
        <Text style={styles.title}>Venda #{vendaId} registrada!</Text>
        <Text style={styles.subtitle}>Aguardando pagamento...</Text>
      </View>

      <View style={styles.section}>
        <Text style={styles.label}>Enviar cupom por e-mail</Text>
        <AppInput
          value={email}
          onChangeText={setEmail}
          placeholder="cliente@email.com"
          keyboardType="email-address"
          autoCapitalize="none"
        />
        <AppButton
          title={enviando ? 'Enviando...' : 'Enviar cupom'}
          onPress={handleCupom}
          variant="outline"
          disabled={!email || enviando}
          small
        />
      </View>

      <View style={styles.footer}>
        <AppButton
          title="Nova venda"
          onPress={() => navigation.navigate('Main')}
          icon="cart"
        />
      </View>
    </Screen>
  );
};

const styles = StyleSheet.create({
  center: { alignItems: 'center', paddingVertical: spacing.xl },
  iconWrap: { marginBottom: spacing.md },
  title: { fontSize: typography.h1, fontWeight: '800', color: colors.ink, textAlign: 'center' },
  subtitle: { fontSize: typography.body, color: colors.muted, marginTop: spacing.xs, textAlign: 'center' },
  section: { padding: spacing.lg, gap: spacing.sm },
  label: { fontSize: typography.small, fontWeight: '700', color: colors.muted },
  footer: { padding: spacing.lg },
});
