<?php
/**
 * views/login.php
 * Tela de entrada do sistema.
 * Se já existir sessão ativa, redireciona direto para o dashboard do perfil.
 */
session_start();

if (isset($_SESSION['id_usuario'])) {
    if ($_SESSION['tipo_perfil'] === 'farmaceutico') {
        header('Location: dashboard_farmaceutico.php');
    } else {
        header('Location: dashboard_auxiliar.php');
    }
    exit;
}

// Mensagem de erro vinda do controller (login_controller.php) via sessão
$erro = $_SESSION['erro_login'] ?? null;
unset($_SESSION['erro_login']);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Sistema de Farmácia Hospitalar</title>
    <link rel="stylesheet" href="../assets/css/login.css">
</head>
<body>

    <div class="login-container">
        <h1>Farmácia Hospitalar</h1>
        <p class="subtitulo">Gerenciamento de Requisições e Estoque</p>

        <?php if ($erro): ?>
            <div class="mensagem-erro"><?= htmlspecialchars($erro) ?></div>
        <?php endif; ?>

        <form action="../controllers/login_controller.php" method="POST">
            <div class="campo">
                <label for="login">Usuário</label>
                <input type="text" id="login" name="login" required autofocus>
            </div>

            <div class="campo">
                <label for="senha">Senha</label>
                <input type="password" id="senha" name="senha" required>
            </div>

            <button type="submit" class="btn-entrar">Entrar</button>
        </form>
    </div>

</body>
</html>
