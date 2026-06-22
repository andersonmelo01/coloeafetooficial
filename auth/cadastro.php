<?php
require_once dirname(__DIR__) . '/includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf();

    try {
        $pdo = db();
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("INSERT INTO usuarios (nome, email, senha_hash, tipo, ativo) VALUES (:nome, :email, :senha, 'cliente', 1)");
        $stmt->execute([
            'nome' => $_POST['nome'],
            'email' => $_POST['email'],
            'senha' => password_hash((string) $_POST['senha'], PASSWORD_DEFAULT),
        ]);
        $usuarioId = (int) $pdo->lastInsertId();

        $stmt = $pdo->prepare(
            "INSERT INTO clientes_perfis
             (usuario_id, documento, inscricao_estadual, telefone, data_nascimento, tipo_pessoa)
             VALUES (:usuario_id, :documento, :inscricao_estadual, :telefone, :data_nascimento, :tipo_pessoa)"
        );
        $stmt->execute([
            'usuario_id' => $usuarioId,
            'documento' => $_POST['documento'],
            'inscricao_estadual' => $_POST['inscricao_estadual'] ?: null,
            'telefone' => $_POST['telefone'],
            'data_nascimento' => $_POST['data_nascimento'] ?: null,
            'tipo_pessoa' => $_POST['tipo_pessoa'],
        ]);

        $stmt = $pdo->prepare(
            "INSERT INTO enderecos
             (usuario_id, cep, logradouro, numero, complemento, bairro, cidade, uf, principal)
             VALUES (:usuario_id, :cep, :logradouro, :numero, :complemento, :bairro, :cidade, :uf, 1)"
        );
        $stmt->execute([
            'usuario_id' => $usuarioId,
            'cep' => $_POST['cep'],
            'logradouro' => $_POST['logradouro'],
            'numero' => $_POST['numero'],
            'complemento' => $_POST['complemento'] ?: null,
            'bairro' => $_POST['bairro'],
            'cidade' => $_POST['cidade'],
            'uf' => strtoupper((string) $_POST['uf']),
        ]);

        $pdo->commit();
        flash('success', 'Cadastro criado. Agora voce ja pode entrar.');
        redirect('auth/login.php');
    } catch (Throwable $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        flash('danger', 'Nao foi possivel criar o cadastro. Verifique se o banco foi importado.');
        redirect('auth/cadastro.php');
    }
}

$pageTitle = 'Cadastro';
require_once dirname(__DIR__) . '/includes/header.php';
?>

<section class="py-5">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-9">
                <div class="panel-card bg-white p-4">
                    <h1 class="h3 section-title">Cadastro do cliente</h1>
                    <p class="text-secondary">Dados necessarios para compra, emissao de NF-e e entrega.</p>
                    <form method="post" class="row g-3">
                        <?= csrf_field() ?>
                        <div class="col-md-8"><label class="form-label">Nome/Razao social</label><input class="form-control" name="nome" required></div>
                        <div class="col-md-4"><label class="form-label">Tipo de pessoa</label><select class="form-select" name="tipo_pessoa"><option value="fisica">Fisica</option><option value="juridica">Juridica</option></select></div>
                        <div class="col-md-6"><label class="form-label">E-mail</label><input class="form-control" name="email" type="email" required></div>
                        <div class="col-md-6"><label class="form-label">Senha</label><input class="form-control" name="senha" type="password" minlength="6" required></div>
                        <div class="col-md-4"><label class="form-label">CPF/CNPJ</label><input class="form-control" name="documento" data-mask="cpfcnpj" required></div>
                        <div class="col-md-4"><label class="form-label">Inscricao estadual</label><input class="form-control" name="inscricao_estadual"></div>
                        <div class="col-md-4"><label class="form-label">Telefone</label><input class="form-control" name="telefone" data-mask="phone" required></div>
                        <div class="col-md-4"><label class="form-label">Nascimento</label><input class="form-control" name="data_nascimento" type="date"></div>
                        <div class="col-md-3"><label class="form-label">CEP</label><input class="form-control" name="cep" data-mask="cep" required></div>
                        <div class="col-md-7"><label class="form-label">Logradouro</label><input class="form-control" name="logradouro" required></div>
                        <div class="col-md-2"><label class="form-label">Numero</label><input class="form-control" name="numero" required></div>
                        <div class="col-md-4"><label class="form-label">Complemento</label><input class="form-control" name="complemento"></div>
                        <div class="col-md-4"><label class="form-label">Bairro</label><input class="form-control" name="bairro" required></div>
                        <div class="col-md-3"><label class="form-label">Cidade</label><input class="form-control" name="cidade" required></div>
                        <div class="col-md-1"><label class="form-label">UF</label><input class="form-control" name="uf" data-mask="uf" maxlength="2" required></div>
                        <div class="col-12"><button class="btn btn-brand" type="submit">Criar cadastro</button></div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
