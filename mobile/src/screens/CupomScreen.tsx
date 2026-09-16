import React, { useCallback, useState } from 'react';
import { Image, ScrollView, Share, StyleSheet, Text, View } from 'react-native';
import { useFocusEffect, useRoute, RouteProp } from '@react-navigation/native';
import * as Clipboard from 'expo-clipboard';
import { Screen } from '../components/Screen';
import { AppButton } from '../components/AppButton';
import { AppInput } from '../components/AppInput';
import { useAuth } from '../contexts/AuthContext';
import { useToast } from '../contexts/ToastContext';
import { getVenda, enviarCupom } from '../api/endpoints';
import { colors, radius, spacing, typography } from '../theme';
import { money, dateTimeBR, dateBR } from '../utils/format';
import { VendaDetalheResponse } from '../types';
import { RootStackParamList } from '../navigation';

type CupomRouteProp = RouteProp<RootStackParamList, 'Cupom'>;

const STATUS_LABEL: Record<string, string> = {
  finalizada: 'Finalizada',
  pendente: 'Pendente',
  cancelada: 'Cancelada',
  aberta: 'Aberta',
};

export const CupomScreen: React.FC = () => {
  const route = useRoute<CupomRouteProp>();
  const { token, config } = useAuth();
  const { show } = useToast();
  const [data, setData] = useState<VendaDetalheResponse | null>(null);
  const [carregando, setCarregando] = useState(true);
  const [email, setEmail] = useState('');
  const [enviando, setEnviando] = useState(false);

  const carregar = useCallback(async () => {
    if (!token) return;
    setCarregando(true);
    try {
      const response = await getVenda(token, route.params.vendaId);
      setData(response);
      if (response.venda.email_recibo) {
        setEmail(response.venda.email_recibo);
      } else if (response.venda.cliente_email) {
        setEmail(response.venda.cliente_email);
      }
    } catch (e) {
      show((e as Error).message, 'error');
    } finally {
      setCarregando(false);
    }
  }, [token, route.params.vendaId]);

  useFocusEffect(
    useCallback(() => {
      carregar();
    }, [carregar])
  );

  const copiarPix = async () => {
    if (!data?.venda.pix_copiaecola) return;
    await Clipboard.setStringAsync(data.venda.pix_copiaecola);
    show('Código Pix copiado.', 'success');
  };

  const compartilhar = async () => {
    if (!data) return;
    const { venda, itens } = data;
    const linhas = itens
      .map((i) => `${i.quantidade}x ${i.nome}  ${i.total_formatado ?? money(i.total)}`)
      .join('\n');
    const texto =
      `${config?.nome_estabelecimento ?? 'Colo & Afeto'}\n` +
      `Cupom ${venda.cupom_no || '-'} · Venda ${venda.numero}\n` +
      `${venda.finalizada_em}\n\n${linhas}\n\n` +
      `Subtotal: ${venda.subtotal_formatado}\nDesconto: ${venda.desconto_formatado}\nTotal: ${venda.total_formatado}`;
    try {
      await Share.share({ message: texto });
    } catch {
      // usuário cancelou
    }
  };

  const enviarEmail = async () => {
    if (!token || !email) return;
    setEnviando(true);
    try {
      const r = await enviarCupom(token, route.params.vendaId, email);
      show(r.message, 'success');
    } catch (e) {
      show((e as Error).message, 'error');
    } finally {
      setEnviando(false);
    }
  };

  if (!data) {
    return (
      <Screen>
        <Text style={styles.loading}>{carregando ? 'Carregando cupom...' : 'Venda não encontrada'}</Text>
      </Screen>
    );
  }

  const { venda, itens, pagamentos, parcelas } = data;
  const saldoPendente = Math.round(
    parcelas.filter((p) => p.status === 'pendente').reduce((s, p) => s + p.valor, 0) * 100
  ) / 100;

  return (
    <Screen>
      <ScrollView contentContainerStyle={styles.content}>
        <View style={styles.paper}>
          <View style={styles.brandBlock}>
            <Text style={styles.brand}>{config?.nome_estabelecimento ?? 'Colo & Afeto'}</Text>
            <Text style={styles.docBadge}>
              {config?.fiscal_habilitado ? 'CUPOM FISCAL — NFC-e' : 'CUPOM NÃO FISCAL — SEM VALOR FISCAL'}
            </Text>
          </View>

          <View style={styles.infoGrid}>
            <Info label="Cupom" value={venda.cupom_no || '—'} />
            <Info label="Venda" value={venda.numero} />
            <Info label="Status" value={STATUS_LABEL[venda.status] ?? venda.status} />
            <Info label="Data" value={dateTimeBR(venda.finalizada_em)} />
            <Info label="Atendente" value={venda.vendedor || '—'} />
            <Info label="Cliente" value={venda.cliente || 'Consumidor não identificado'} />
          </View>

          <Text style={styles.section}>Itens</Text>
          {itens.map((it, idx) => (
            <View key={String(it.id ?? it.produto_id ?? idx)} style={styles.itemBlock}>
              <Text style={styles.itemName}>{it.nome}</Text>
              <View style={styles.itemMeta}>
                <Text style={styles.itemMetaText}>
                  {it.quantidade} x {it.preco_unitario_formatado ?? money(it.preco_unitario)}
                </Text>
                <Text style={styles.itemTotal}>{it.total_formatado ?? money(it.total)}</Text>
              </View>
            </View>
          ))}

          <View style={styles.totals}>
            <Linha label="Subtotal" value={venda.subtotal_formatado} />
            <Linha label="Desconto" value={venda.desconto_formatado} />
            <Linha label="TOTAL" value={venda.total_formatado} strong />
          </View>

          {pagamentos.length > 0 && (
            <>
              <Text style={styles.section}>Pagamentos</Text>
              {pagamentos.map((p) => (
                <Linha
                  key={p.id}
                  label={`${p.metodo_label}${p.status === 'pendente' ? ' (pendente)' : ''}`}
                  value={p.valor_formatado}
                />
              ))}
            </>
          )}

          {parcelas.length > 0 && (
            <>
              <Text style={styles.section}>A receber — parcelas</Text>
              {parcelas.map((p) => (
                <View key={p.id} style={styles.parcelaRow}>
                  <Text style={styles.parcelaLabel}>
                    {p.parcela}ª parcela · {dateBR(p.vencimento)}
                    {p.status === 'pago' ? ' · pago' : ''}
                  </Text>
                  <Text style={styles.parcelaValue}>{p.valor_formatado}</Text>
                </View>
              ))}
              {saldoPendente > 0 && <Linha label="Saldo a receber" value={money(saldoPendente)} strong />}
            </>
          )}

          {!!venda.pix_copiaecola && (
            <>
              <Text style={styles.section}>Pix copia e cola</Text>
              {!!venda.pix_qrcode && (
                <View style={styles.qrWrap}>
                  <Image
                    source={{ uri: `data:image/png;base64,${venda.pix_qrcode}` }}
                    style={styles.qr}
                  />
                </View>
              )}
              <Text style={styles.pixCode} selectable>
                {venda.pix_copiaecola}
              </Text>
              <AppButton title="Copiar código Pix" variant="ghost" small onPress={copiarPix} />
            </>
          )}

          {!!venda.nfce_chave && (
            <>
              <Text style={styles.section}>Chave de acesso NFC-e</Text>
              <Text style={styles.pixCode} selectable>
                {venda.nfce_chave}
              </Text>
            </>
          )}

          <Text style={styles.footerNote}>
            Obrigado pela preferência!{'\n'}
            {config?.nome_estabelecimento ?? 'Colo & Afeto'} · Atendimento materno-infantil
          </Text>
        </View>

        <AppButton title="Compartilhar cupom" variant="outline" onPress={compartilhar} />

        <View style={styles.emailBox}>
          <Text style={styles.section}>Enviar cupom por e-mail</Text>
          <AppInput
            value={email}
            onChangeText={setEmail}
            placeholder="cliente@email.com"
            keyboardType="email-address"
            autoCapitalize="none"
          />
          <AppButton
            title={enviando ? 'Enviando...' : 'Enviar cupom'}
            variant="outline"
            onPress={enviarEmail}
            disabled={!email || enviando}
            small
          />
        </View>
      </ScrollView>
    </Screen>
  );
};

const Info: React.FC<{ label: string; value: string }> = ({ label, value }) => (
  <View style={styles.infoCell}>
    <Text style={styles.infoLabel}>{label}</Text>
    <Text style={styles.infoValue}>{value}</Text>
  </View>
);

const Linha: React.FC<{ label: string; value: string; strong?: boolean }> = ({
  label,
  value,
  strong,
}) => (
  <View style={styles.line}>
    <Text style={[styles.lineLabel, strong && styles.lineStrong]}>{label}</Text>
    <Text style={[styles.lineValue, strong && styles.lineStrong]}>{value}</Text>
  </View>
);

const styles = StyleSheet.create({
  content: { padding: spacing.lg, gap: spacing.md, paddingBottom: 40 },
  loading: { textAlign: 'center', color: colors.muted, marginTop: spacing.xl },
  paper: {
    backgroundColor: colors.surface,
    borderRadius: radius.md,
    padding: spacing.lg,
    borderWidth: 1,
    borderColor: colors.border,
  },
  brandBlock: { alignItems: 'center', borderBottomWidth: 1, borderBottomColor: colors.border, paddingBottom: spacing.md },
  brand: { fontSize: typography.h2, fontWeight: '800', color: colors.primaryDark },
  docBadge: {
    marginTop: 6,
    fontSize: typography.tiny,
    fontWeight: '700',
    color: colors.muted,
    textAlign: 'center',
  },
  infoGrid: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    marginTop: spacing.md,
    rowGap: spacing.sm,
  },
  infoCell: { width: '50%' },
  infoLabel: { fontSize: typography.tiny, color: colors.textLight, textTransform: 'uppercase' },
  infoValue: { fontSize: typography.small, color: colors.ink, fontWeight: '600' },
  section: {
    fontSize: typography.small,
    fontWeight: '800',
    color: colors.muted,
    marginTop: spacing.lg,
    marginBottom: spacing.xs,
  },
  itemBlock: {
    borderBottomWidth: StyleSheet.hairlineWidth,
    borderBottomColor: colors.border,
    paddingVertical: 6,
  },
  itemName: { fontSize: typography.body, color: colors.ink },
  itemMeta: { flexDirection: 'row', justifyContent: 'space-between', marginTop: 2 },
  itemMetaText: { fontSize: typography.small, color: colors.muted },
  itemTotal: { fontSize: typography.small, color: colors.ink, fontWeight: '700' },
  totals: {
    marginTop: spacing.md,
    borderTopWidth: 1,
    borderTopColor: colors.border,
    paddingTop: spacing.sm,
  },
  line: { flexDirection: 'row', justifyContent: 'space-between', paddingVertical: 3 },
  lineLabel: { fontSize: typography.body, color: colors.muted },
  lineValue: { fontSize: typography.body, color: colors.ink, fontWeight: '600' },
  lineStrong: { fontWeight: '800', color: colors.ink, fontSize: typography.h3 },
  parcelaRow: { flexDirection: 'row', justifyContent: 'space-between', paddingVertical: 3 },
  parcelaLabel: { fontSize: typography.small, color: colors.muted, flex: 1, paddingRight: spacing.sm },
  parcelaValue: { fontSize: typography.small, color: colors.ink, fontWeight: '600' },
  qrWrap: { alignItems: 'center', marginVertical: spacing.sm },
  qr: { width: 190, height: 190, resizeMode: 'contain' },
  pixCode: {
    fontSize: typography.tiny,
    color: colors.textLight,
    backgroundColor: colors.cream,
    borderRadius: radius.sm,
    padding: spacing.sm,
    marginBottom: spacing.sm,
  },
  footerNote: {
    marginTop: spacing.lg,
    fontSize: typography.tiny,
    color: colors.textLight,
    textAlign: 'center',
    lineHeight: 16,
  },
  emailBox: {
    backgroundColor: colors.surface,
    borderRadius: radius.md,
    padding: spacing.md,
    gap: spacing.sm,
  },
});
