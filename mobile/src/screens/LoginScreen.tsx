import React, { useState } from 'react';
import {
  ActivityIndicator,
  KeyboardAvoidingView,
  Platform,
  Pressable,
  StyleSheet,
  Text,
  TextInput,
  View,
} from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { useAuth } from '../contexts/AuthContext';
import { useToast } from '../contexts/ToastContext';
import { AppButton } from '../components/AppButton';
import { AppInput } from '../components/AppInput';
import { colors, radius, shadow, spacing, typography } from '../theme';

export const LoginScreen: React.FC = () => {
  const { signIn, setServerUrl } = useAuth();
  const { show } = useToast();
  const [email, setEmail] = useState('');
  const [senha, setSenha] = useState('');
  const [url, setUrl] = useState('');
  const [busy, setBusy] = useState(false);
  const [showUrl, setShowUrl] = useState(false);

  const handleLogin = async () => {
    if (!email.trim() || !senha.trim()) {
      show('Informe e-mail e senha.', 'warning');
      return;
    }
    setBusy(true);
    try {
      if (url.trim()) {
        await setServerUrl(url.trim());
      }
      await signIn(email.trim(), senha);
    } catch (e: any) {
      show(e?.message || 'Não foi possível entrar.', 'error');
    } finally {
      setBusy(false);
    }
  };

  return (
    <KeyboardAvoidingView
      style={styles.wrap}
      behavior={Platform.OS === 'ios' ? 'padding' : undefined}
    >
      <View style={styles.hero}>
        <View style={styles.logoWrap}>
          <Ionicons name="heart" size={56} color={colors.primary} />
        </View>
        <Text style={styles.brand}>Colo & Afeto</Text>
        <Text style={styles.subtitle}>PDV Mobile</Text>
        <Text style={styles.tagline}>Venda com carinho, no seu celular.</Text>
      </View>

      <View style={styles.card}>
        <AppInput
          label="E-mail"
          value={email}
          onChangeText={setEmail}
          placeholder="seu@email.com"
          keyboardType="email-address"
          autoCapitalize="none"
        />
        <AppInput
          label="Senha"
          value={senha}
          onChangeText={setSenha}
          placeholder="••••••"
          secure
        />

        <Pressable style={styles.urlToggle} onPress={() => setShowUrl((s) => !s)}>
          <Ionicons
            name={showUrl ? 'chevron-up' : 'chevron-down'}
            size={16}
            color={colors.textLight}
          />
          <Text style={styles.urlToggleText}>
            {showUrl ? 'Ocultar servidor' : 'Configurar servidor'}
          </Text>
        </Pressable>
        {showUrl && (
          <AppInput
            label="URL da API"
            value={url}
            onChangeText={setUrl}
            placeholder="http://192.168.0.10/ColoAfeto/api/pdv"
            autoCapitalize="none"
            hint="Deixe vazio para usar a URL já salva."
          />
        )}

        <AppButton
          title="Entrar"
          onPress={handleLogin}
          loading={busy}
          icon={<Ionicons name="log-in-outline" size={18} color="#fff" />}
        />
      </View>

      <Text style={styles.footer}>ColoAfeto • v1.0.0</Text>
      {busy && (
        <View style={styles.progress}>
          <ActivityIndicator color={colors.primary} />
        </View>
      )}
    </KeyboardAvoidingView>
  );
};

const styles = StyleSheet.create({
  wrap: {
    flex: 1,
    backgroundColor: colors.cream,
    paddingHorizontal: spacing.lg,
  },
  hero: {
    alignItems: 'center',
    marginTop: spacing.xl * 2,
    marginBottom: spacing.xl,
  },
  logoWrap: {
    width: 84,
    height: 84,
    borderRadius: radius.xl,
    backgroundColor: colors.surface,
    alignItems: 'center',
    justifyContent: 'center',
    ...shadow.float,
  },
  brand: {
    marginTop: spacing.md,
    fontSize: typography.title,
    fontWeight: '800',
    color: colors.ink,
  },
  subtitle: {
    fontSize: typography.h2,
    fontWeight: '700',
    color: colors.primaryDark,
    letterSpacing: 2,
  },
  tagline: {
    marginTop: spacing.sm,
    fontSize: typography.body,
    color: colors.muted,
  },
  card: {
    backgroundColor: colors.surface,
    borderRadius: radius.lg,
    padding: spacing.lg,
    ...shadow.card,
  },
  urlToggle: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    marginBottom: spacing.sm,
    paddingVertical: 4,
  },
  urlToggleText: {
    fontSize: typography.small,
    color: colors.info,
    fontWeight: '600',
  },
  footer: {
    textAlign: 'center',
    marginTop: spacing.xl,
    fontSize: typography.tiny,
    color: colors.textLight,
  },
  progress: {
    position: 'absolute',
    bottom: 34,
    alignSelf: 'center',
  },
});
