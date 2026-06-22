<?php
require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_once dirname(__DIR__, 2) . '/includes/NFeService.php';
require_login('admin');

$acao = (string) ($_GET['acao'] ?? $_POST['acao'] ?? '');
$notaId = (int) ($_GET['nota_id'] ?? $_POST['nota_id'] ?? 0);
$nota = db_one(
    "SELECT nf.*, p.id AS pedido_id
     FROM notas_fiscais nf
     JOIN pedidos p ON p.id = nf.pedido_id
     WHERE nf.id = :id",
    ['id' => $notaId]
);

if (!$nota) {
    flash('danger', 'NF-e nao encontrada.');
    redirect('admin/faturamento/index.php');
}

if ($acao === 'abrir') {
    $path = nfe_xml_absolute_path($nota['xml_path'] ?? $nota['xml_url'] ?? null);
    if (!$path) {
        flash('warning', 'XML nao encontrado para esta NF-e.');
        redirect('admin/faturamento/nota.php?id=' . $notaId);
    }

    header('Content-Type: application/xml; charset=UTF-8');
    header('Content-Disposition: inline; filename="' . basename($path) . '"');
    readfile($path);
    exit;
}

validate_csrf();

if ($acao === 'salvar_xml') {
    $status = in_array($_POST['status'] ?? 'pendente', ['pendente', 'transmitida', 'autorizada', 'cancelada', 'rejeitada', 'corrigida'], true)
        ? $_POST['status']
        : 'pendente';
    $relativePath = $nota['xml_path'] ?? null;

    if (!empty($_FILES['xml_file']['name'])) {
        if (($_FILES['xml_file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            flash('danger', 'Nao foi possivel receber o arquivo XML.');
            redirect('admin/faturamento/nota.php?id=' . $notaId);
        }

        $extension = strtolower(pathinfo((string) $_FILES['xml_file']['name'], PATHINFO_EXTENSION));
        if ($extension !== 'xml') {
            flash('danger', 'Envie somente arquivo XML da NF-e.');
            redirect('admin/faturamento/nota.php?id=' . $notaId);
        }

        $filename = 'nfe-' . $notaId . '-' . date('YmdHis') . '.xml';
        $target = nfe_storage_dir() . '/' . $filename;
        if (!move_uploaded_file((string) $_FILES['xml_file']['tmp_name'], $target)) {
            flash('danger', 'Nao foi possivel salvar o XML.');
            redirect('admin/faturamento/nota.php?id=' . $notaId);
        }

        $relativePath = 'storage/nfe/' . $filename;
    }

    db()->prepare(
        "UPDATE notas_fiscais
         SET numero = :numero, serie = :serie, chave_acesso = :chave_acesso, protocolo = :protocolo,
             status = :status, xml_path = :xml_path, xml_url = :xml_path,
             emitida_em = CASE WHEN :status_emitida IN ('autorizada', 'corrigida') THEN COALESCE(emitida_em, NOW()) ELSE emitida_em END
         WHERE id = :id"
    )->execute([
        'id' => $notaId,
        'numero' => trim((string) ($_POST['numero'] ?? '')) ?: null,
        'serie' => trim((string) ($_POST['serie'] ?? '')) ?: null,
        'chave_acesso' => trim((string) ($_POST['chave_acesso'] ?? '')) ?: null,
        'protocolo' => trim((string) ($_POST['protocolo'] ?? '')) ?: null,
        'status' => $status,
        'status_emitida' => $status,
        'xml_path' => $relativePath,
    ]);

    nfe_register_event((int) $nota['pedido_id'], 'xml_nfe', 'salvo', 'XML e dados da NF-e atualizados.', ['nota_id' => $notaId]);
    flash('success', 'Dados da NF-e salvos.');
    redirect('admin/faturamento/nota.php?id=' . $notaId);
}

if ($acao === 'cancelar') {
    $motivo = trim((string) ($_POST['motivo_cancelamento'] ?? ''));
    if (strlen($motivo) < 15) {
        flash('warning', 'Informe um motivo de cancelamento com pelo menos 15 caracteres.');
        redirect('admin/faturamento/nota.php?id=' . $notaId);
    }

    db()->prepare(
        "UPDATE notas_fiscais
         SET status = 'cancelada', motivo_cancelamento = :motivo, cancelada_em = NOW()
         WHERE id = :id"
    )->execute(['id' => $notaId, 'motivo' => $motivo]);

    nfe_register_event((int) $nota['pedido_id'], 'cancelamento_nfe', 'pendente_sefaz', 'Cancelamento registrado no sistema. Enviar evento pela SPED-NFe para concluir na SEFAZ.', ['nota_id' => $notaId, 'motivo' => $motivo]);
    flash('success', 'Cancelamento registrado. Em producao, confirme o envio do evento na SEFAZ pela SPED-NFe.');
    redirect('admin/faturamento/nota.php?id=' . $notaId);
}

if ($acao === 'corrigir') {
    $correcao = trim((string) ($_POST['carta_correcao'] ?? ''));
    if (strlen($correcao) < 15) {
        flash('warning', 'Informe a correcao com pelo menos 15 caracteres.');
        redirect('admin/faturamento/nota.php?id=' . $notaId);
    }

    db()->prepare(
        "UPDATE notas_fiscais
         SET status = 'corrigida', carta_correcao = :correcao, corrigida_em = NOW()
         WHERE id = :id"
    )->execute(['id' => $notaId, 'correcao' => $correcao]);

    nfe_register_event((int) $nota['pedido_id'], 'cce_nfe', 'pendente_sefaz', 'Carta de correcao registrada no sistema. Enviar evento CC-e pela SPED-NFe para concluir na SEFAZ.', ['nota_id' => $notaId, 'correcao' => $correcao]);
    flash('success', 'Carta de correcao registrada. Em producao, confirme o envio do evento CC-e na SEFAZ.');
    redirect('admin/faturamento/nota.php?id=' . $notaId);
}

flash('warning', 'Acao fiscal invalida.');
redirect('admin/faturamento/nota.php?id=' . $notaId);
