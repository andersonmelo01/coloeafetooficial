<?php
declare(strict_types=1);

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/EmailService.php';

function pdv_fiscal_mode(): bool
{
    return fiscal_enabled();
}

function pdv_cupom_label(): string
{
    return pdv_fiscal_mode() ? 'Cupom fiscal — NFC-e' : 'Cupom não fiscal';
}

function pdv_ensure_cupom_no(int $vendaId): string
{
    $venda = db_one("SELECT id, cupom_no FROM vendas WHERE id = :id", ['id' => $vendaId]);
    if (!$venda) {
        return '';
    }

    if (!empty($venda['cupom_no'])) {
        return (string) $venda['cupom_no'];
    }

    $no = 'CC-' . date('Ymd') . '-' . str_pad((string) $vendaId, 6, '0', STR_PAD_LEFT);
    db()->prepare("UPDATE vendas SET cupom_no = :cupom_no WHERE id = :id")->execute([
        'cupom_no' => $no,
        'id' => $vendaId,
    ]);

    return $no;
}

function pdv_nfce_dv(string $chave43): string
{
    $soma = 0;
    $mult = 2;
    for ($i = strlen($chave43) - 1; $i >= 0; $i--) {
        $soma += ((int) $chave43[$i]) * $mult;
        $mult = $mult === 9 ? 2 : $mult + 1;
    }

    $resto = $soma % 11;
    return (string) ($resto > 1 ? (11 - $resto) : 0);
}

function pdv_ensure_nfce_chave(int $vendaId): string
{
    $venda = db_one("SELECT id, nfce_chave FROM vendas WHERE id = :id", ['id' => $vendaId]);
    if (!$venda) {
        return '';
    }

    if (!empty($venda['nfce_chave'])) {
        return (string) $venda['nfce_chave'];
    }

    $ufMap = [
        'AC' => 12, 'AL' => 27, 'AM' => 13, 'AP' => 16, 'BA' => 29, 'CE' => 23, 'DF' => 53, 'ES' => 32,
        'GO' => 52, 'MA' => 21, 'MG' => 31, 'MS' => 50, 'MT' => 51, 'PA' => 15, 'PB' => 25, 'PE' => 26,
        'PI' => 22, 'PR' => 41, 'RJ' => 33, 'RN' => 24, 'RO' => 11, 'RR' => 14, 'RS' => 43, 'SC' => 42,
        'SE' => 28, 'SP' => 35, 'TO' => 17,
    ];

    $uf = strtoupper((string) app_config('fiscal.uf', 'RJ'));
    $cUF = str_pad((string) ($ufMap[$uf] ?? 33), 2, '0', STR_PAD_LEFT);
    $cnpj = preg_replace('/\D/', '', (string) app_config('fiscal.cnpj', ''));
    $cnpj = str_pad($cnpj, 14, '0', STR_PAD_LEFT);
    $aamm = date('ym');
    $mod = '65';
    $serie = str_pad((string) app_config('fiscal.serie_nfe', '1'), 3, '0', STR_PAD_LEFT);
    $numero = str_pad((string) app_config('fiscal.proximo_numero_nfe', '1'), 9, '0', STR_PAD_LEFT);
    $tpEmis = '9';
    $cNF = substr(bin2hex(random_bytes(8)), 0, 8);

    $chave43 = $cUF . $aamm . $cnpj . $mod . $serie . $numero . $tpEmis . $cNF;
    $dv = pdv_nfce_dv($chave43);
    $chave = $chave43 . $dv;

    db()->prepare("UPDATE vendas SET nfce_chave = :nfce_chave WHERE id = :id")->execute([
        'nfce_chave' => $chave,
        'id' => $vendaId,
    ]);

    try {
        db()->prepare(
            "INSERT INTO notas_fiscais (pedido_id, numero, serie, chave_acesso, status, emitida_em)
             VALUES (NULL, :numero, :serie, :chave, 'pendente', NOW())"
        )->execute([
            'numero' => $numero,
            'serie' => $serie,
            'chave' => $chave,
        ]);
    } catch (Throwable $e) {
        // Falha ao registrar nota não bloqueia impressão do cupom.
    }

    return $chave;
}

function pdv_cupom_has_value($value): bool
{
    return trim((string) $value) !== '';
}

function pdv_cupom_info_line(string $label, $value): string
{
    if (!pdv_cupom_has_value($value)) {
        return '';
    }

    return '<div class="cupom-info-line"><span>' . e($label) . '</span><strong>' . e((string) $value) . '</strong></div>';
}

function pdv_cupom_inline_pairs(array $pairs): string
{
    $out = '';
    foreach ($pairs as $label => $value) {
        if (!pdv_cupom_has_value($value)) {
            continue;
        }
        $out .= '<span><b>' . e((string) $label) . ':</b> ' . e((string) $value) . '</span>';
    }
    return $out;
}

function pdv_cupom_date(?string $dateTime, string $format = 'd/m/Y H:i'): string
{
    $dateTime = trim((string) $dateTime);
    if ($dateTime === '') {
        return '';
    }

    $ts = strtotime($dateTime);
    return $ts ? date($format, $ts) : $dateTime;
}

function pdv_cupom_percent($value): string
{
    $number = (float) $value;
    return $number > 0 ? number_format($number, 2, ',', '.') . '%' : '';
}

function pdv_cupom_first_config(array $keys): string
{
    foreach ($keys as $key) {
        $value = trim((string) app_config($key, ''));
        if ($value !== '') {
            return $value;
        }
    }

    return '';
}

function pdv_cupom_tax_label($aliquota, float $base): string
{
    $percent = (float) $aliquota;
    if ($percent <= 0) {
        return '';
    }

    return money_br(round($base * ($percent / 100), 2)) . ' (' . pdv_cupom_percent($percent) . ')';
}

function pdv_cupom_html(int $vendaId, bool $showPrint = false): string
{
    $venda = db_one(
        "SELECT v.*,
                vendedor.nome AS vendedor,
                cliente.nome AS cliente,
                cliente.email AS cliente_email,
                cp.tipo_pessoa AS cliente_tipo_pessoa,
                cp.documento AS cliente_documento,
                cp.inscricao_estadual AS cliente_ie,
                cp.telefone AS cliente_telefone,
                ender.cep AS cliente_cep,
                ender.logradouro AS cliente_logradouro,
                ender.numero AS cliente_numero,
                ender.complemento AS cliente_complemento,
                ender.bairro AS cliente_bairro,
                ender.cidade AS cliente_cidade,
                ender.uf AS cliente_uf
         FROM vendas v
         LEFT JOIN usuarios vendedor ON vendedor.id = v.usuario_id
         LEFT JOIN usuarios cliente ON cliente.id = v.cliente_id
         LEFT JOIN clientes_perfis cp ON cp.usuario_id = cliente.id
         LEFT JOIN enderecos ender ON ender.usuario_id = cliente.id
            AND ender.id = (
                SELECT e2.id FROM enderecos e2
                WHERE e2.usuario_id = cliente.id
                ORDER BY e2.principal DESC, e2.id DESC
                LIMIT 1
            )
         WHERE v.id = :id",
        ['id' => $vendaId]
    );
    if (!$venda) {
        return '<p class="text-danger">Venda não encontrada.</p>';
    }

    $cupomNo = pdv_ensure_cupom_no($vendaId);
    $isFiscal = pdv_fiscal_mode();
    $nfceChave = '';
    if ($isFiscal) {
        $nfceChave = pdv_ensure_nfce_chave($vendaId);
    }

    $itens = db_all(
        "SELECT vi.*,
                p.sku,
                p.ncm,
                p.cest,
                p.cfop,
                p.unidade,
                p.origem_mercadoria,
                p.cst_icms,
                p.csosn,
                p.cst_pis,
                p.cst_cofins,
                p.aliquota_icms,
                p.aliquota_pis,
                p.aliquota_cofins,
                p.cst_ibs_cbs,
                p.cclass_trib_ibs_cbs,
                p.aliquota_ibs_uf,
                p.aliquota_ibs_municipal,
                p.aliquota_cbs,
                p.cst_is,
                p.cclass_trib_is,
                p.aliquota_is
         FROM venda_itens vi
         LEFT JOIN produtos p ON p.id = vi.produto_id
         WHERE vi.venda_id = :id
         ORDER BY vi.id",
        ['id' => $vendaId]
    );
    $pagamentos = db_all("SELECT * FROM venda_pagamentos WHERE venda_id = :id ORDER BY id", ['id' => $vendaId]);
    $parcelasAPrazo = db_all("SELECT * FROM venda_parcelas WHERE venda_id = :id AND status = 'pendente' ORDER BY parcela", ['id' => $vendaId]);
    $saldoAPrazo = round(array_sum(array_column($parcelasAPrazo, 'valor')), 2);
    $vendaPix = db_one("SELECT pix_copiaecola, pix_qrcode FROM vendas WHERE id = :id", ['id' => $vendaId]);

    $subtotal = (float) $venda['subtotal'];
    $desconto = (float) $venda['desconto'];
    $total = (float) $venda['total'];
    $descontoPct = $subtotal > 0 ? round(($desconto / $subtotal) * 100, 1) : 0.0;
    $dataVendaFmt = pdv_cupom_date((string) ($venda['finalizada_em'] ?? $venda['criado_em'] ?? ''));
    $dataCriacaoFmt = pdv_cupom_date((string) ($venda['criado_em'] ?? ''));
    $dataAtualizacaoFmt = pdv_cupom_date((string) ($venda['atualizado_em'] ?? ''));
    $cliente = trim((string) ($venda['cliente'] ?? ''));
    $vendedor = trim((string) ($venda['vendedor'] ?? ''));
    $totalItens = array_sum(array_map(static fn (array $item): int => (int) $item['quantidade'], $itens));
    $emitenteNome = trim((string) app_config('fiscal.razao_social', ''));
    $emitenteNome = $emitenteNome !== '' ? $emitenteNome : 'Colo & Afeto';
    $emitenteUf = strtoupper(trim((string) app_config('fiscal.uf', '')));
    $fiscalAmbiente = trim((string) app_config('fiscal.ambiente', 'homologacao'));
    $fiscalSerie = trim((string) app_config('fiscal.serie_nfe', '1'));
    $emitenteTelefone = pdv_cupom_first_config([
        'estabelecimento.telefone',
        'loja.telefone',
        'empresa.telefone',
        'contato.telefone',
        'whatsapp.numero',
    ]);
    $emitenteEndereco = trim(implode(', ', array_filter([
        trim((string) app_config('fiscal.endereco_logradouro', '')),
        trim((string) app_config('fiscal.endereco_numero', '')),
    ], 'pdv_cupom_has_value')));
    $emitenteCidadeUf = trim(implode(' - ', array_filter([
        trim((string) app_config('fiscal.endereco_municipio', '')),
        $emitenteUf,
    ], 'pdv_cupom_has_value')));
    $clienteEndereco = trim(implode(', ', array_filter([
        trim((string) ($venda['cliente_logradouro'] ?? '')),
        trim((string) ($venda['cliente_numero'] ?? '')),
        trim((string) ($venda['cliente_complemento'] ?? '')),
    ], 'pdv_cupom_has_value')));
    $clienteCidadeUf = trim(implode(' - ', array_filter([
        trim((string) ($venda['cliente_cidade'] ?? '')),
        trim((string) ($venda['cliente_uf'] ?? '')),
    ], 'pdv_cupom_has_value')));
    $taxTotals = [
        'ICMS' => 0.0,
        'PIS' => 0.0,
        'COFINS' => 0.0,
        'IBS UF' => 0.0,
        'IBS Mun.' => 0.0,
        'CBS' => 0.0,
        'IS' => 0.0,
    ];
    foreach ($itens as $item) {
        $base = (float) $item['total'];
        $taxTotals['ICMS'] += round($base * (((float) ($item['aliquota_icms'] ?? 0)) / 100), 2);
        $taxTotals['PIS'] += round($base * (((float) ($item['aliquota_pis'] ?? 0)) / 100), 2);
        $taxTotals['COFINS'] += round($base * (((float) ($item['aliquota_cofins'] ?? 0)) / 100), 2);
        $taxTotals['IBS UF'] += round($base * (((float) ($item['aliquota_ibs_uf'] ?? 0)) / 100), 2);
        $taxTotals['IBS Mun.'] += round($base * (((float) ($item['aliquota_ibs_municipal'] ?? 0)) / 100), 2);
        $taxTotals['CBS'] += round($base * (((float) ($item['aliquota_cbs'] ?? 0)) / 100), 2);
        $taxTotals['IS'] += round($base * (((float) ($item['aliquota_is'] ?? 0)) / 100), 2);
    }
    $totalImpostos = round(array_sum($taxTotals), 2);

    $out = '<div class="cupom">';
    if ($showPrint) {
        $out .= '<div class="cupom-actions d-print-none">';
        $out .= '<button type="button" class="btn btn-brand" onclick="window.print()"><i class="bi bi-printer"></i> Imprimir</button>';
        $out .= '<a class="btn btn-outline-secondary" href="javascript:history.back()"><i class="bi bi-arrow-left"></i> Voltar</a>';
        $out .= '</div>';
    }
    $out .= '<div class="cupom-paper">';

    $out .= '<div class="cupom-header">';
    $out .= '<div class="cupom-brand">';
    $out .= '<strong>Colo&Afeto</strong>';
    $out .= '</div>';
    if ($isFiscal) {
        $out .= '<div class="cupom-fiscal-badge">CUPOM FISCAL — NFC-e</div>';
        $out .= '<div class="cupom-meta small">Emissão em modo offline / contingência</div>';
    } else {
        $out .= '<div class="cupom-fiscal-badge cupom-nao-fiscal">CUPOM NÃO FISCAL - SEM VALOR FISCAL</div>';
        $out .= '<div class="cupom-meta small">Documento auxiliar interno com informações fiscais cadastradas</div>';
    }
    $out .= '</div>';

    $out .= '<div class="cupom-body">';

    $out .= '<div class="cupom-section">';
    $out .= '<div class="cupom-section-title">Emitente</div>';
    $out .= '<div class="cupom-info-stack">';
    $out .= pdv_cupom_info_line('Razão social', $emitenteNome);
    $out .= pdv_cupom_info_line('CNPJ', app_config('fiscal.cnpj', ''));
    $out .= pdv_cupom_info_line('Inscrição estadual', app_config('fiscal.inscricao_estadual', ''));
    $out .= pdv_cupom_info_line('Endereço', $emitenteEndereco);
    $out .= pdv_cupom_info_line('Bairro', app_config('fiscal.endereco_bairro', ''));
    $out .= pdv_cupom_info_line('Município/UF', $emitenteCidadeUf);
    $out .= pdv_cupom_info_line('CEP', app_config('fiscal.endereco_cep', ''));
    $out .= pdv_cupom_info_line('Telefone', $emitenteTelefone);
    $out .= '</div></div>';

    $out .= '<div class="cupom-info-grid">';
    $out .= '<div><span>Cupom</span><strong>' . e($cupomNo) . '</strong></div>';
    $out .= '<div><span>Venda</span><strong>' . e((string) $venda['numero']) . '</strong></div>';
    $out .= '<div><span>Status</span><strong>' . e((string) $venda['status']) . '</strong></div>';
    $out .= '<div><span>Modelo</span><strong>65 - NFC-e</strong></div>';
    $out .= '<div><span>Série</span><strong>' . e($fiscalSerie) . '</strong></div>';
    $out .= '<div><span>Ambiente</span><strong>' . e($fiscalAmbiente) . '</strong></div>';
    $out .= '<div><span>Emissão</span><strong>' . ($isFiscal ? 'Fiscal habilitado' : 'Não fiscal') . '</strong></div>';
    if (pdv_cupom_has_value($venda['caixa_id'] ?? '')) {
        $out .= '<div><span>Caixa</span><strong>#' . (int) $venda['caixa_id'] . '</strong></div>';
    }
    if ($dataVendaFmt !== '') {
        $out .= '<div><span>Data</span><strong>' . e($dataVendaFmt) . '</strong></div>';
    }
    if ($vendedor !== '') {
        $out .= '<div><span>Atendente</span><strong>' . e($vendedor) . '</strong></div>';
    }
    $out .= '</div>';

    $out .= '<div class="cupom-section mt-3">';
    $out .= '<div class="cupom-section-title">Consumidor</div>';
    $out .= '<div class="cupom-info-stack">';
    $out .= pdv_cupom_info_line('Nome', $cliente !== '' ? $cliente : 'Consumidor não identificado');
    if ($isFiscal) {
        $out .= pdv_cupom_info_line('E-mail', $venda['cliente_email'] ?? '');
        $out .= pdv_cupom_info_line('Documento', $venda['cliente_documento'] ?? '');
        $out .= pdv_cupom_info_line('Tipo pessoa', $venda['cliente_tipo_pessoa'] ?? '');
        $out .= pdv_cupom_info_line('Inscrição estadual', $venda['cliente_ie'] ?? '');
        $out .= pdv_cupom_info_line('Telefone', $venda['cliente_telefone'] ?? '');
        $out .= pdv_cupom_info_line('Endereço', $clienteEndereco);
        $out .= pdv_cupom_info_line('Bairro', $venda['cliente_bairro'] ?? '');
        $out .= pdv_cupom_info_line('Cidade/UF', $clienteCidadeUf);
        $out .= pdv_cupom_info_line('CEP', $venda['cliente_cep'] ?? '');
    }
    $out .= '</div></div>';

    $out .= '<div class="cupom-items mt-3 mb-3">';
    $out .= '<div class="cupom-section-title">Itens</div>';
    foreach ($itens as $item) {
        $out .= '<div class="cupom-item">';
        $out .= '<div class="cupom-item-name">' . e($item['nome_produto']) . '</div>';
        $out .= '<div class="cupom-item-meta">';
        $unit = pdv_cupom_has_value($item['unidade'] ?? '') ? (string) $item['unidade'] : 'UN';
        $out .= '<span>' . (int) $item['quantidade'] . ' ' . e($unit) . ' x ' . money_br((float) $item['preco_unitario']) . '</span>';
        $out .= '<strong>' . money_br((float) $item['total']) . '</strong>';
        $out .= '</div>';
        $baseItem = (float) $item['total'];
        $itemTaxes = pdv_cupom_inline_pairs([
            'ICMS' => pdv_cupom_tax_label($item['aliquota_icms'] ?? 0, $baseItem),
            'PIS' => pdv_cupom_tax_label($item['aliquota_pis'] ?? 0, $baseItem),
            'COFINS' => pdv_cupom_tax_label($item['aliquota_cofins'] ?? 0, $baseItem),
            'IBS UF' => pdv_cupom_tax_label($item['aliquota_ibs_uf'] ?? 0, $baseItem),
            'IBS Mun.' => pdv_cupom_tax_label($item['aliquota_ibs_municipal'] ?? 0, $baseItem),
            'CBS' => pdv_cupom_tax_label($item['aliquota_cbs'] ?? 0, $baseItem),
            'IS' => pdv_cupom_tax_label($item['aliquota_is'] ?? 0, $baseItem),
        ]);
        if ($itemTaxes !== '') {
            $out .= '<div class="cupom-item-tax">Impostos: ' . $itemTaxes . '</div>';
        }
        $itemFiscal = pdv_cupom_inline_pairs([
            'Cod' => $item['produto_id'] ?? '',
            'SKU' => $item['sku'] ?? '',
            'NCM' => $item['ncm'] ?? '',
            'CEST' => $item['cest'] ?? '',
            'CFOP' => $item['cfop'] ?? '',
            'Origem' => $item['origem_mercadoria'] ?? '',
            'CST ICMS' => $item['cst_icms'] ?? '',
            'CSOSN' => $item['csosn'] ?? '',
            'CST PIS' => $item['cst_pis'] ?? '',
            'CST COFINS' => $item['cst_cofins'] ?? '',
            'Aliq. ICMS' => pdv_cupom_percent($item['aliquota_icms'] ?? 0),
            'Aliq. PIS' => pdv_cupom_percent($item['aliquota_pis'] ?? 0),
            'Aliq. COFINS' => pdv_cupom_percent($item['aliquota_cofins'] ?? 0),
            'CST IBS/CBS' => $item['cst_ibs_cbs'] ?? '',
            'Classe IBS/CBS' => $item['cclass_trib_ibs_cbs'] ?? '',
            'Aliq. IBS UF' => pdv_cupom_percent($item['aliquota_ibs_uf'] ?? 0),
            'Aliq. IBS Mun.' => pdv_cupom_percent($item['aliquota_ibs_municipal'] ?? 0),
            'Aliq. CBS' => pdv_cupom_percent($item['aliquota_cbs'] ?? 0),
            'CST IS' => $item['cst_is'] ?? '',
            'Classe IS' => $item['cclass_trib_is'] ?? '',
            'Aliq. IS' => pdv_cupom_percent($item['aliquota_is'] ?? 0),
        ]);
        if ($itemFiscal !== '') {
            $out .= '<div class="cupom-item-fiscal">' . $itemFiscal . '</div>';
        }
        $out .= '</div>';
    }
    $out .= '</div>';

    $out .= '<div class="cupom-totals">';
    $out .= '<div class="cupom-section-title">Totais</div>';
    $out .= '<div class="cupom-total-line"><span>Quantidade de itens</span><span>' . (int) $totalItens . '</span></div>';
    $out .= '<div class="cupom-total-line"><span>Subtotal</span><span>' . money_br($subtotal) . '</span></div>';
    if ($desconto > 0) {
        $out .= '<div class="cupom-total-line text-danger"><span>Desconto (−' . number_format($descontoPct, 1, ',', '.') . '%)</span><span>−' . money_br($desconto) . '</span></div>';
    }
    if ($totalImpostos > 0) {
        $out .= '<div class="cupom-total-line"><span>Impostos informados</span><span>' . money_br($totalImpostos) . '</span></div>';
    }
    $out .= '<div class="cupom-total-line cupom-total-final"><span>TOTAL</span><span>' . money_br($total) . '</span></div>';
    $out .= '</div>';

    if ($totalImpostos > 0) {
        $out .= '<div class="cupom-tax-summary mt-2">';
        $out .= '<div class="cupom-section-title">Resumo de impostos</div>';
        foreach ($taxTotals as $label => $value) {
            if ($value <= 0) {
                continue;
            }
            $out .= '<div class="cupom-total-line"><span>' . e($label) . '</span><span>' . money_br($value) . '</span></div>';
        }
        $out .= '</div>';
    }

    if ($pagamentos) {
        $out .= '<div class="cupom-payments mt-3 mb-2">';
        $out .= '<div class="cupom-section-title">Pagamentos registrados</div>';
        foreach ($pagamentos as $pg) {
            $out .= '<div class="cupom-pay-line">';
            $out .= '<span>' . e(pdv_payment_label($pg['metodo'])) . '<small>' . e(pdv_cupom_date((string) ($pg['pago_em'] ?? $pg['criado_em'] ?? ''))) . '</small></span>';
            $out .= '<span class="cupom-pay-value">';
            if ($pg['status'] === 'pendente') {
                $out .= '<small class="cupom-status text-warning">pendente</small>';
            }
            $out .= '<span>' . money_br((float) $pg['valor']) . '</span>';
            $out .= '</span>';
            $out .= '</div>';
        }
        $out .= '</div>';
    }

    $dadosAdicionais = '';
    $dadosAdicionais .= pdv_cupom_info_line('Observação', $venda['observacao'] ?? '');
    if (pdv_cupom_has_value($venda['efi_charge_id'] ?? '')) {
        $dadosAdicionais .= pdv_cupom_info_line('Cobrança Efi', $venda['efi_charge_id']);
    }
    if (pdv_cupom_has_value($venda['efi_charge_status'] ?? '')) {
        $dadosAdicionais .= pdv_cupom_info_line('Status Efi', $venda['efi_charge_status']);
    }
    if (pdv_cupom_has_value($venda['pix_txid'] ?? '')) {
        $dadosAdicionais .= pdv_cupom_info_line('TxID Pix', $venda['pix_txid']);
    }
    if ($dadosAdicionais !== '') {
        $out .= '<div class="cupom-section mt-2">';
        $out .= '<div class="cupom-section-title">Dados adicionais</div>';
        $out .= '<div class="cupom-info-stack">' . $dadosAdicionais . '</div>';
        $out .= '</div>';
    }

    if ($saldoAPrazo > 0) {
        $out .= '<div class="cupom-parcelas mt-2 mb-3">';
        $out .= '<div class="cupom-section-title">A receber — parcelas pendentes</div>';
        $out .= '<table class="table table-sm table-borderless cupom-tbl mb-0">';
        $out .= '<tbody>';
        foreach ($parcelasAPrazo as $par) {
            $out .= '<tr>';
            $out .= '<td>' . (int) $par['parcela'] . 'ª parcela</td>';
            $out .= '<td>' . date('d/m/Y', strtotime($par['vencimento'])) . '</td>';
            $out .= '<td class="text-end">' . money_br((float) $par['valor']) . '</td>';
            $out .= '</tr>';
        }
        $out .= '<tr class="cupom-pend-total"><td colspan="2">Saldo a receber</td><td class="text-end">' . money_br($saldoAPrazo) . '</td></tr>';
        $out .= '</tbody></table></div>';
    }

    if (!empty($vendaPix['pix_copiaecola'])) {
        $out .= '<div class="cupom-pix mt-3 mb-3">';
        $out .= '<div class="cupom-section-title">Pix copia e cola</div>';
        $out .= '<div class="pix-copiaecola">' . e($vendaPix['pix_copiaecola']) . '</div>';
        if (!empty($vendaPix['pix_qrcode'])) {
            $out .= '<div class="mt-2 text-center">';
            $out .= '<img class="cupom-qrcode" src="data:image/png;base64,' . e($vendaPix['pix_qrcode']) . '" alt="QR Code Pix">';
            $out .= '</div>';
        }
        $out .= '</div>';
    }

    if ($isFiscal && $nfceChave !== '') {
        $out .= '<div class="cupom-chave mt-3 mb-2">';
        $out .= '<div class="cupom-meta">Chave de acesso NFC-e</div>';
        $out .= '<div class="cupom-meta cupom-key fw-semibold">' . e($nfceChave) . '</div>';
        $out .= '<div class="cupom-meta">Consulta: <a href="https://www.sefaz.rs.gov.br/Consulta/ConsultaNFCe.aspx" target="_blank" rel="noopener">Portal SEFAZ</a></div>';
        $out .= '</div>';
    }

    $out .= '<div class="cupom-footer mt-4 text-center">';
    $out .= '<hr>';
    $out .= '<p>Obrigado pela preferência!</p>';
    $out .= '<p class="small text-secondary">Colo & Afeto · Atendimento materno-infantil</p>';
    if ($isFiscal) {
        $out .= '<p class="small text-secondary">Este documento não substitui a NF-e (mod 55) quando aplicável.</p>';
    } else {
        $out .= '<p class="small text-secondary">Documento auxiliar interno. Não substitui NFC-e, NF-e, NFS-e ou qualquer documento fiscal autorizado.</p>';
    }
    $out .= '</div>';

    $out .= '</div>';
    $out .= '</div>';
    $out .= '</div>';

    return $out;
}

function pdv_cupom_send_email(int $vendaId, string $toEmail, string $toName = ''): array
{
    $venda = db_one("SELECT id, numero FROM vendas WHERE id = :id", ['id' => $vendaId]);
    if (!$venda) {
        return ['ok' => false, 'message' => 'Venda não encontrada.'];
    }

    if (trim($toEmail) === '' || !filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'message' => 'E-mail do destinatário inválido.'];
    }

    pdv_ensure_cupom_no($vendaId);
    $label = pdv_cupom_label();
    $subject = $label . ' ' . (string) $venda['numero'] . ' — Colo & Afeto';
    $body = '<div style="font-family:\'Segoe UI\',Arial,sans-serif;max-width:420px;margin:0 auto;color:#222;">';
    $body .= pdv_cupom_html($vendaId, false);
    $body .= '</div>';

    $ok = email_send(
        (int) $vendaId,
        $toEmail,
        $toName !== '' ? $toName : 'Cliente',
        $subject,
        $body,
        strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $body))
    );

    return ['ok' => $ok, 'message' => $ok ? 'E-mail enviado com sucesso.' : 'Falha ao enviar e-mail.'];
}
