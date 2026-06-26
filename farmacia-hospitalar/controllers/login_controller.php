<?php
/**
 * controllers/login_controller.php
 * Recebe o POST do formulário de login, valida no banco via PDO
 * e direciona para a view correta de acordo com o tipo_perfil.
 *
 * Este controller atende DUAS origens:
 *  - index.html (raiz, estático)      -> em caso de erro, volta para ../index.html?erro=1
 *  - views/login.php (PHP, com sessão) -> em caso de erro, usa $_SESSION['erro_login']
 * A origem é detectada pelo header Referer enviado pelo navegador.
 */
session_start();
require_once __DIR__ . '/../config/db.php';

// Detecta se o POST veio do index.html estático da raiz ou do views/login.php
$referer = $_SERVER['HTTP_REFERER'] ?? '';
$veioDoIndexEstatico = (strpos($referer, 'index.html') !== false);

/**
 * Centraliza o redirecionamento de erro, respeitando a origem da requisição.
 */
function redirecionarErro(string $mensagem, bool $veioDoIndexEstatico): void
{
    if ($veioDoIndexEstatico) {
        // index.html não lê sessão PHP, então o erro vai via query string
        header('Location: ../index.html?erro=1');
    } else {
        $_SESSION['erro_login'] = $mensagem;
        header('Location: ../views/login.php');
    }
    exit;
}

// Garante que a requisição veio via POST (evita acesso direto pela URL)
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirecionarErro('Acesso inválido.', $veioDoIndexEstatico);
}

$login = trim($_POST['login'] ?? '');
$senha = trim($_POST['senha'] ?? '');

if ($login === '' || $senha === '') {
    redirecionarErro('Preencha usuário e senha.', $veioDoIndexEstatico);
}

// Busca o usuário pelo login OU pelo e-mail (o campo do form aceita os dois)
$sql = "SELECT u.id_usuario, u.nome, u.email, u.senha, u.crf, u.tipo_perfil, u.ativo,
               u.id_farmacia, f.sigla AS sigla_farmacia
        FROM usuarios u
        INNER JOIN farmacias f ON f.id_farmacia = u.id_farmacia
        WHERE u.login = :login OR u.email = :email
        LIMIT 1";

$stmt = $pdo->prepare($sql);
$stmt->execute(['login' => $login, 'email' => $login]);
$usuario = $stmt->fetch();

// Valida existência, senha (hash) e status ativo
if (!$usuario || !password_verify($senha, $usuario['senha']) || (int)$usuario['ativo'] !== 1) {
    redirecionarErro('Usuário ou senha inválidos.', $veioDoIndexEstatico);
}

// Login válido: grava os dados necessários na sessão
$_SESSION['id_usuario']     = $usuario['id_usuario'];
$_SESSION['nome']           = $usuario['nome'];
$_SESSION['crf']            = $usuario['crf'];
$_SESSION['tipo_perfil']    = $usuario['tipo_perfil'];   // 'auxiliar', 'farmaceutico' ou 'admin'
$_SESSION['id_farmacia']    = $usuario['id_farmacia'];
$_SESSION['sigla_farmacia'] = $usuario['sigla_farmacia'];

// Direciona conforme o perfil
// Admin usa o mesmo painel do farmacêutico, que já tem a aba "Equipe"
if (in_array($usuario['tipo_perfil'], ['farmaceutico', 'admin'])) {
    header('Location: ../views/dashboard_farmaceutico.php');
} else {
    header('Location: ../views/dashboard_auxiliar.php');
}
exit;
