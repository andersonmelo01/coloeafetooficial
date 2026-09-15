import React, { useCallback, useState } from 'react';
import { Alert, ScrollView, StyleSheet, Text, View } from 'react-native';
import { useFocusEffect } from '@react-navigation/native';
import { Screen } from '../components/Screen';
import { AppButton } from '../components/AppButton';
import { AppInput } from '../components/AppInput';
import { useAuth } from '../contexts/AuthContext';
import { useToast } from '../contexts/ToastContext';
import { getCaixa, caixaAction } from '../api/endpoints';
import { colors, radius, spacing, typography } from '../theme';
import { money } from '../utils/format';
import { CaixaResponse } from '../types';

export const CaixaScreen: React.FC = () => {
  const { token, refreshCaixa, caixa } = useAuth();
  const { show } = useToast();
  const [data, setData] = useState<CaixaResponse | null>(null);
  const [carregando, setCarregando] = useState(false);
  const [saldoInicial, setSaldoInicial] = useState(0);
  const [valorMov, setValorMov] = useState(0);
  const [saldoFinal, setSaldoFinal] = useState(0);

  const carregar = useCallback(async () => {
    if (!token) return;
    setCarregando(true);
    try {
      const r = await getCaixa(token);
      setData(r);
      refreshCaixa(r.caixa ? { id: r.caixa.id, aberto_em: r.caixa.aberto_em, saldo: r.totais.saldo } : null);
    } catch (e) {
      show((e as Error).message, 'error');
    } finally {
      setCarregando(false);
    }
  }, [token]);

  useFocusEffect(
    useCallback(() => {
      carregar();
    }, [carregar])
  );

  const acao = async (body: Record<string, unknown>, confirmMsg?: string) => {
    if (!token) return;
    const run = async () => {
      try {
        const r = await caixaAction(token, body);
        show(r.message, 'success');
        await carregar();
      } catch (e) {
        show((e as Error).message, 'error');
      }
    };
    if (confirmMsg) {
      Alert.alert('Confirmar', confirmMsg, [
        { text: 'Não', style: 'cancel' },
        { text: 'Sim', onPress: run },
      ]);
    } else {
      await run();
    }
  };

  const aberto = !!caixa;

  return (
    <Screen>
      <ScrollView contentContainerStyle={styles.content}>
        <View style={styles.statusCard}>
          <Text style={styles.statusTitle}>{aberto ? 'Caixa aberto' : 'Caixa fechado'}</Text>
          {aberto && caixa && <Text style={styles.statusMeta}>Desde {caixa.aberto_em}</Text>}
          {data && (
            <Text style={styles.saldo}>Saldo atual: {money(data.totais.saldo)}</Text>
          )}
        </View>

        {!aberto && (
          <View style={styles.section}>
            <Text style={styles.sectionTitle}>Abrir caixa</Text>
            <AppInput
              label="Saldo inicial"
              value={String(saldoInicial)}
              onChangeText={(t) => setSaldoInicial(Number(t.replace(',', '.')) || 0)}
              keyboardType="decimal-pad"
            />
            <AppButton
              title="Abrir caixa"
              onPress={() => acao({ acao: 'abrir', saldo_inicial: saldoInicial })}
              icon="lock-open"
            />
          </View>
        )}

        {aberto && (
          <>
            <View style={styles.section}>
              <Text style={styles.sectionTitle}>Movimentação</Text>
              <AppInput
                label="Valor"
                value={String(valorMov)}
                onChangeText={(t) => setValorMov(Number(t.replace(',', '.')) || 0)}
                keyboardType="decimal-pad"
              />
              <View style={styles.btnRow}>
                <AppButton
                  title="Suprimento"
                  variant="success"
                  onPress={() => acao({ acao: 'suprimento', valor: valorMov })}
                  small
                />
                <AppButton
                  title="Sangria"
                  variant="danger"
                  onPress={() => acao({ acao: 'sangria', valor: valorMov })}
                  small
                />
              </View>
            </View>

            {data && (
              <View style={styles.section}>
                <Text style={styles.sectionTitle}>Totais do dia</Text>
                {Object.entries(data.totais_formatados).map(([k, v]) => (
                  <View key={k} style={styles.line}>
                    <Text style={styles.lineName}>{k.replace(/_/g, ' ')}</Text>
                    <Text style={styles.lineValue}>{v}</Text>
                  </View>
                ))}
              </View>
            )}

            <View style={styles.section}>
              <Text style={styles.sectionTitle}>Fechar caixa</Text>
              <AppInput
                label="Saldo final (contado)"
                value={String(saldoFinal)}
                onChangeText={(t) => setSaldoFinal(Number(t.replace(',', '.')) || 0)}
                keyboardType="decimal-pad"
              />
              <AppButton
                title="Fechar caixa"
                variant="dark"
                onPress={() =>
                  acao(
                    { acao: 'fechar', saldo_final: saldoFinal },
                    'Confirmar fechamento do caixa?'
                  )
                }
                icon="lock-closed"
              />
            </View>
          </>
        )}
      </ScrollView>
    </Screen>
  );
};

const styles = StyleSheet.create({
  content: { padding: spacing.lg, gap: spacing.lg },
  statusCard: {
    backgroundColor: colors.primaryLight,
    borderRadius: radius.lg,
    padding: spacing.lg,
  },
  statusTitle: { fontSize: typography.h2, fontWeight: '800', color: colors.primaryDark },
  statusMeta: { fontSize: typography.small, color: colors.muted, marginTop: 2 },
  saldo: { fontSize: typography.h3, fontWeight: '700', color: colors.ink, marginTop: spacing.sm },
  section: { gap: spacing.sm },
  sectionTitle: { fontSize: typography.h3, fontWeight: '800', color: colors.ink },
  btnRow: { flexDirection: 'row', gap: spacing.md },
  line: { flexDirection: 'row', justifyContent: 'space-between', paddingVertical: 3 },
  lineName: { fontSize: typography.body, color: colors.muted, textTransform: 'capitalize' },
  lineValue: { fontSize: typography.body, color: colors.ink, fontWeight: '600' },
});
