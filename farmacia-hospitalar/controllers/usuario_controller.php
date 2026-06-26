<?php
/**
 * controllers/usuario_controller.php
 * Controller "AJAX" para a área de Perfil e Gestão de Usuários.
 * Responde em JSON. Ações disponíveis via parâmetro "action":
 *
 *   - meu_perfil       (GET)  -> dados do usuário logado (qualquer perfil)
 *   - trocar_farmacia  (POST) -> o próprio usuário troca sua farmácia (qualquer perfil)
 *   - listar_farmacias (GET)  -> lista farmácias ativas (qualquer perfil, usado no seletor)
 *   - listar_usuarios  (GET)  -> lista usuários cadastrados (somente farmaceutico/admin)
 *   - cadastrar_usuario(POST) -> cria novo usuário com senha padrão (somente farmaceutico/admin)
 *   - minhas_atendidas (GET)  -> requisições já atendidas/canceladas pelo usuário logado
 */

require_once __DIR__ . '/../includes/auth_check.php'; // garante sessão ativa
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json; charset=utf-8');

$action = $_REQUEST['action'] ?? '';

// Perfis que podem gerenciar outros usuários (ver lista e cadastrar)
$perfisGestores = ['farmaceutico', 'admin'];

switch ($action) {
    case 'meu_perfil':
        meuPerfil($pdo);
        break;

    case 'trocar_farmacia':
        trocarFarmacia($pdo);
        break;

    case 'listar_farmacias':
        listarFarmacias($pdo);
        break;

    case 'listar_usuarios':
        exigirPerfilGestor($perfisGestores);
        listarUsuarios($pdo);
        break;

    case 'cadastrar_usuario':
        exigirPerfilGestor($perfisGestores);
        cadastrarUsuario($pdo);
        break;

    case 'minhas_atendidas':
        minhasAtendidas($pdo);
        break;

    default:
        http_response_code(400);
        echo json_encode(['erro' => 'Ação inválida.']);
}

/**
 * Bloqueia a ação se o perfil da sessão não estiver na lista permitida.
 */
function exigirPerfilGestor(array $perfisPermitidos): void
{
    if (!in_array($_SESSION['tipo_perfil'], $perfisPermitidos)) {
        http_response_code(403);
        echo json_encode(['erro' => 'Você não tem permissão para esta ação.']);
        exit;
    }
}

/**
 * Retorna os dados do usuário logado para preencher a tela de Perfil.
 */
function meuPerfil(PDO $pdo): void
{
    $sql = "SELECT u.id_usuario, u.nome, u.email, u.login, u.crf, u.tipo_perfil,
                   u.id_farmacia, f.sigla AS sigla_farmacia, f.nome_completo AS nome_farmacia
            FROM usuarios u
            INNER JOIN farmacias f ON f.id_farmacia = u.id_farmacia
            WHERE u.id_usuario = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['id' => $_SESSION['id_usuario']]);
    $usuario = $stmt->fetch();

    if (!$usuario) {
        http_response_code(404);
        echo json_encode(['erro' => 'Usuário não encontrado.']);
        return;
    }

    echo json_encode($usuario);
}

/**
 * Lista as farmácias ativas, usado no seletor de troca de farmácia
 * e no formulário de cadastro de usuário.
 */
function listarFarmacias(PDO $pdo): void
{
    $farmacias = $pdo->query(
        "SELECT id_farmacia, sigla, nome_completo FROM farmacias WHERE ativo = 1 ORDER BY sigla"
    )->fetchAll();
    echo json_encode($farmacias);
}

/**
 * O próprio usuário logado troca a farmácia em que atua.
 * Troca livre e imediata, sem aprovação, conforme regra de negócio definida.
 */
function trocarFarmacia(PDO $pdo): void
{
    $dados = json_decode(file_get_contents('php://input'), true);
    $idFarmacia = (int)($dados['id_farmacia'] ?? 0);

    if (!$idFarmacia) {
        http_response_code(400);
        echo json_encode(['erro' => 'Selecione uma farmácia válida.']);
        return;
    }

    $stmt = $pdo->prepare("UPDATE usuarios SET id_farmacia = :id_farmacia WHERE id_usuario = :id_usuario");
    $stmt->execute(['id_farmacia' => $idFarmacia, 'id_usuario' => $_SESSION['id_usuario']]);

    // Atualiza a sessão imediatamente para refletir a troca sem precisar logar de novo
    $stmtFarmacia = $pdo->prepare("SELECT sigla FROM farmacias WHERE id_farmacia = :id");
    $stmtFarmacia->execute(['id' => $idFarmacia]);
    $farmacia = $stmtFarmacia->fetch();

    $_SESSION['id_farmacia'] = $idFarmacia;
    $_SESSION['sigla_farmacia'] = $farmacia['sigla'] ?? '';

    echo json_encode(['sucesso' => true, 'sigla_farmacia' => $_SESSION['sigla_farmacia']]);
}

/**
 * Lista todos os usuários cadastrados, com o nome da farmácia de cada um.
 * Restrito a farmacêutico/admin (checado antes de chamar esta função).
 */
function listarUsuarios(PDO $pdo): void
{
    $sql = "SELECT u.id_usuario, u.nome, u.email, u.login, u.crf, u.tipo_perfil, u.ativo,
                   f.sigla AS sigla_farmacia, f.nome_completo AS nome_farmacia
            FROM usuarios u
            INNER JOIN farmacias f ON f.id_farmacia = u.id_farmacia
            ORDER BY f.sigla, u.tipo_perfil, u.nome";
    $usuarios = $pdo->query($sql)->fetchAll();
    echo json_encode($usuarios);
}

/**
 * Cadastra um novo usuário (auxiliar, farmacêutico ou admin) com senha padrão.
 * Restrito a farmacêutico/admin (checado antes de chamar esta função).
 */
function cadastrarUsuario(PDO $pdo): void
{
    $dados = json_decode(file_get_contents('php://input'), true);

    $nome = trim($dados['nome'] ?? '');
    $email = trim($dados['email'] ?? '');
    $login = trim($dados['login'] ?? '');
    $tipoPerfil = $dados['tipo_perfil'] ?? '';
    $idFarmacia = (int)($dados['id_farmacia'] ?? 0);
    $crf = trim($dados['crf'] ?? '');

    $perfisValidos = ['auxiliar', 'farmaceutico', 'admin'];

    if ($nome === '' || $email === '' || $login === '' || !$idFarmacia || !in_array($tipoPerfil, $perfisValidos)) {
        http_response_code(400);
        echo json_encode(['erro' => 'Preencha nome, e-mail, login, farmácia e um perfil válido.']);
        return;
    }

    // Senha padrão automática, conforme definido: a pessoa troca depois pelo próprio perfil
    $senhaPadrao = '123456';
    $hashSenha = password_hash($senhaPadrao, PASSWORD_DEFAULT);

    try {
        $stmt = $pdo->prepare(
            "INSERT INTO usuarios (nome, login, email, senha, crf, tipo_perfil, id_farmacia)
             VALUES (:nome, :login, :email, :senha, :crf, :tipo_perfil, :id_farmacia)"
        );
        $stmt->execute([
            'nome' => $nome,
            'login' => $login,
            'email' => $email,
            'senha' => $hashSenha,
            'crf' => $crf !== '' ? $crf : null,
            'tipo_perfil' => $tipoPerfil,
            'id_farmacia' => $idFarmacia,
        ]);

        echo json_encode([
            'sucesso' => true,
            'senha_padrao' => $senhaPadrao, // devolvido uma única vez, para informar a quem cadastrou
        ]);
    } catch (PDOException $e) {
        // Erro mais comum aqui: login ou email duplicado (violação de UNIQUE)
        http_response_code(400);
        echo json_encode(['erro' => 'Não foi possível cadastrar. O login ou e-mail já pode estar em uso.']);
    }
}

/**
 * Lista as requisições que o usuário logado já atendeu ou cancelou
 * (aba "Requisições Atendidas", separada da fila inicial).
 */
function minhasAtendidas(PDO $pdo): void
{
    $sql = "SELECT r.id_requisicao, r.nome_paciente, r.setor_origem, r.prioridade,
                   r.status, r.criado_em, r.atendido_em,
                   fo.sigla AS sigla_origem
            FROM requisicoes r
            INNER JOIN farmacias fo ON fo.id_farmacia = r.id_farmacia_origem
            WHERE r.id_usuario_atendente = :id_usuario
              AND r.status IN ('Atendido', 'Cancelado')
            ORDER BY r.atendido_em DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['id_usuario' => $_SESSION['id_usuario']]);
    $requisicoes = $stmt->fetchAll();

    $sqlItens = "SELECT m.nome, i.quantidade_solicitada, i.numero_lote_dispensado
                 FROM itens_requisicao i
                 INNER JOIN medicamentos m ON m.id_medicamento = i.id_medicamento
                 WHERE i.id_requisicao = :id_requisicao";
    $stmtItens = $pdo->prepare($sqlItens);

    foreach ($requisicoes as &$req) {
        $stmtItens->execute(['id_requisicao' => $req['id_requisicao']]);
        $req['itens'] = $stmtItens->fetchAll();
    }

    echo json_encode($requisicoes);
}
