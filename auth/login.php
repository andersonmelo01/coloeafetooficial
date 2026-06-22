<?php
require_once dirname(__DIR__) . '/includes/functions.php';

$databaseReady = true;
$databaseError = '';

try {
    ensure_database_schema();
} catch (Throwable $e) {
    $databaseReady = false;
    $databaseError = $e->getMessage();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf();

    if (!$databaseReady) {
        flash('danger', 'Nao foi possivel preparar o banco de dados. Verifique usuario, senha e servico MySQL.');
        redirect('auth/login.php');
    }

    $email = trim((string) ($_POST['email'] ?? ''));
    $senha = (string) ($_POST['senha'] ?? '');
    $usuario = db_one("SELECT * FROM usuarios WHERE email = :email AND ativo = 1", ['email' => $email]);

    if ($usuario && password_verify($senha, $usuario['senha_hash'])) {
        $_SESSION['usuario'] = [
            'id' => (int) $usuario['id'],
            'nome' => $usuario['nome'],
            'email' => $usuario['email'],
            'tipo' => $usuario['tipo'],
            'perfil_admin_id' => !empty($usuario['perfil_admin_id']) ? (int) $usuario['perfil_admin_id'] : null,
        ];

        $next = $_SESSION['redirect_after_login'] ?? null;
        unset($_SESSION['redirect_after_login']);
        $homeByType = [
            'admin' => 'admin/index.php',
            'cliente' => 'cliente/index.php',
            'entregador' => 'entregador/index.php',
        ];
        header('Location: ' . ($next ?: base_url($homeByType[$usuario['tipo']] ?? 'cliente/index.php')));
        exit;
    }

    flash('danger', 'E-mail ou senha invalidos.');
    redirect('auth/login.php');
}

$pageTitle = 'Entrar';
require_once dirname(__DIR__) . '/includes/header.php';
?>

<section class="py-5">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-5">
                <div class="panel-card bg-white p-4">
                    <h1 class="h3 section-title">Entrar</h1>
                    <?php if (!$databaseReady): ?>
                        <div class="alert alert-danger small">
                            Nao foi possivel criar ou validar a base de dados automaticamente.
                            <br>Detalhe: <?= e($databaseError) ?>
                        </div>
                    <?php endif; ?>
                    <form method="post" class="mt-4">
                        <?= csrf_field() ?>
                        <div class="mb-3">
                            <label class="form-label" for="email">E-mail</label>
                            <input class="form-control" id="email" name="email" type="email" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="senha">Senha</label>
                            <input class="form-control" id="senha" name="senha" type="password" required>
                        </div>
                        <button class="btn btn-brand w-100" type="submit">Entrar</button>
                    </form>
                    <p class="mt-3 mb-0 small">Ainda nao tem cadastro? <a href="<?= e(base_url('auth/cadastro.php')) ?>">Criar conta</a></p>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
