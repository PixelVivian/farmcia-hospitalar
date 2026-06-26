<?php
/**
 * includes/auth_check.php
 * Inclua este arquivo no topo de toda view protegida.
 * Garante que existe sessão ativa e, opcionalmente, valida o perfil exigido.
 *
 * Uso simples (só exige estar logado):
 *      require_once __DIR__ . '/../includes/auth_check.php';
 *
 * Uso com restrição de perfil (ex: só farmacêutico pode entrar):
 *      define('PERFIL_EXIGIDO', 'farmaceutico');
 *      require_once __DIR__ . '/../includes/auth_check.php';
 *
 * Uso com múltiplos perfis permitidos (ex: farmacêutico OU admin):
 *      define('PERFIL_EXIGIDO', ['farmaceutico', 'admin']);
 *      require_once __DIR__ . '/../includes/auth_check.php';
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['id_usuario'])) {
    header('Location: ../index.html');
    exit;
}

// Se a view definiu PERFIL_EXIGIDO antes de incluir este arquivo,
// bloqueia o acesso de quem não tiver o perfil correto.
// Aceita tanto uma string única quanto uma lista de perfis permitidos.
if (defined('PERFIL_EXIGIDO')) {
    $perfisPermitidos = is_array(PERFIL_EXIGIDO) ? PERFIL_EXIGIDO : [PERFIL_EXIGIDO];
    if (!in_array($_SESSION['tipo_perfil'], $perfisPermitidos)) {
        http_response_code(403);
        echo 'Acesso não permitido para o seu perfil.';
        exit;
    }
}
