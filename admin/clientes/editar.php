<?php
require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_login('admin');

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$cliente = db_one(
    "SELECT u.*, cp.documento, cp.telefone, cp.tipo_pessoa, cp.inscricao_estadual, e.cep, e.logradouro, e.numero, e.complemento, e.bairro, e.cidade, e.uf
     FROM usuarios u
     LEFT JOIN clientes_perfis cp ON cp.usuario_id = u.id
     LEFT JOIN enderecos e ON e.usuario_id = u.id AND e.principal = 1
     WHERE u.id = :id AND u.tipo = 'cliente'",
    ['id' => $id]
);
if (!$cliente) {
    flash('danger', 'Cliente nao encontrado.');
    redirect('admin/clientes/index.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf();
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $pdo->prepare("UPDATE usuarios SET nome = :nome, email = :email, ativo = :ativo WHERE id = :id")->execute(['id' => $id, 'nome' => $_POST['nome'], 'email' => $_POST['email'], 'ativo' => isset($_POST['ativo']) ? 1 : 0]);
        $pdo->prepare("UPDATE clientes_perfis SET documento = :documento, telefone = :telefone, tipo_pessoa = :tipo_pessoa, inscricao_estadual = :inscricao_estadual WHERE usuario_id = :id")->execute(['id' => $id, 'documento' => $_POST['documento'], 'telefone' => $_POST['telefone'], 'tipo_pessoa' => $_POST['tipo_pessoa'], 'inscricao_estadual' => $_POST['inscricao_estadual'] ?: null]);
        $pdo->prepare("UPDATE enderecos SET cep = :cep, logradouro = :logradouro, numero = :numero, complemento = :complemento, bairro = :bairro, cidade = :cidade, uf = :uf WHERE usuario_id = :id AND principal = 1")->execute(['id' => $id, 'cep' => $_POST['cep'], 'logradouro' => $_POST['logradouro'], 'numero' => $_POST['numero'], 'complemento' => $_POST['complemento'] ?: null, 'bairro' => $_POST['bairro'], 'cidade' => $_POST['cidade'], 'uf' => strtoupper((string) $_POST['uf'])]);
        $pdo->commit();
        flash('success', 'Cliente atualizado.');
    } catch (Throwable $e) {
        $pdo->rollBack();
        flash('danger', 'Nao foi possivel atualizar o cliente.');
    }
    redirect('admin/clientes/index.php');
}

$pageTitle = 'Editar Cliente';
$active = 'admin';
require_once dirname(__DIR__, 2) . '/includes/header.php';
?>
<section class="py-5"><div class="container"><div class="row g-4"><div class="col-lg-3"><?php require dirname(__DIR__) . '/menu.php'; ?></div><div class="col-lg-9"><div class="panel-card bg-white p-4">
    <h1 class="h3 section-title">Editar cliente</h1>
    <form method="post" class="row g-3"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $cliente['id'] ?>">
        <div class="col-md-8"><label class="form-label">Nome</label><input class="form-control" name="nome" value="<?= e($cliente['nome']) ?>" required></div><div class="col-md-4"><label class="form-label">Tipo pessoa</label><select class="form-select" name="tipo_pessoa"><option value="fisica" <?= $cliente['tipo_pessoa'] === 'fisica' ? 'selected' : '' ?>>Fisica</option><option value="juridica" <?= $cliente['tipo_pessoa'] === 'juridica' ? 'selected' : '' ?>>Juridica</option></select></div>
        <div class="col-md-6"><label class="form-label">E-mail</label><input class="form-control" name="email" type="email" value="<?= e($cliente['email']) ?>" required></div><div class="col-md-3"><label class="form-label">CPF/CNPJ</label><input class="form-control" name="documento" data-mask="cpfcnpj" value="<?= e($cliente['documento']) ?>" required></div><div class="col-md-3"><label class="form-label">Telefone</label><input class="form-control" name="telefone" data-mask="phone" value="<?= e($cliente['telefone']) ?>" required></div>
        <div class="col-md-4"><label class="form-label">Inscricao estadual</label><input class="form-control" name="inscricao_estadual" value="<?= e($cliente['inscricao_estadual']) ?>"></div><div class="col-md-3"><label class="form-label">CEP</label><input class="form-control" name="cep" data-mask="cep" value="<?= e($cliente['cep']) ?>"></div><div class="col-md-5"><label class="form-label">Logradouro</label><input class="form-control" name="logradouro" value="<?= e($cliente['logradouro']) ?>"></div>
        <div class="col-md-2"><label class="form-label">Numero</label><input class="form-control" name="numero" value="<?= e($cliente['numero']) ?>"></div><div class="col-md-4"><label class="form-label">Complemento</label><input class="form-control" name="complemento" value="<?= e($cliente['complemento']) ?>"></div><div class="col-md-3"><label class="form-label">Bairro</label><input class="form-control" name="bairro" value="<?= e($cliente['bairro']) ?>"></div><div class="col-md-2"><label class="form-label">Cidade</label><input class="form-control" name="cidade" value="<?= e($cliente['cidade']) ?>"></div><div class="col-md-1"><label class="form-label">UF</label><input class="form-control" name="uf" data-mask="uf" value="<?= e($cliente['uf']) ?>" maxlength="2"></div>
        <div class="col-12"><label class="form-check"><input class="form-check-input" type="checkbox" name="ativo" <?= (int) $cliente['ativo'] ? 'checked' : '' ?>> Ativo</label></div><div class="col-12"><button class="btn btn-brand">Salvar</button></div>
    </form>
</div></div></div></div></section>
<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
