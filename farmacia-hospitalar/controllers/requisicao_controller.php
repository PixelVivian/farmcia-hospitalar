<?php
/**
 * controllers/requisicao_controller.php
 * Controller "AJAX" usado pelo dashboard (views/dashboard_farmaceutico.php).
 * Responde em JSON. Ações disponíveis via parâmetro "action":
 *   - listar         (GET)  -> lista requisições + estatísticas
 *   - detalhe         (GET)  -> detalhe de uma requisição com seus itens
 *   - dispensar       (POST) -> marca como Atendido e grava o lote de cada item
 *   - cancelar        (POST) -> marca como Cancelado
 *   - criar           (POST) -> cria uma nova requisição com itens
 */

require_once __DIR__ . '/../includes/auth_check.php'; // garante sessão ativa
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json; charset=utf-8');

$action = $_REQUEST['action'] ?? '';

switch ($action) {
    case 'listar':
        listarRequisicoes($pdo);
        break;

    case 'detalhe':
        detalheRequisicao($pdo);
        break;

    case 'dispensar':
        dispensarRequisicao($pdo);
        break;

    case 'cancelar':
        cancelarRequisicao($pdo);
        break;

    case 'criar':
        criarRequisicao($pdo);
        break;

    default:
        http_response_code(400);
        echo json_encode(['erro' => 'Ação inválida.']);
}

/**
 * Lista as requisições da farmácia do usuário logado (farmácia destino),
 * já com o nome da farmácia de origem, e calcula os contadores do dashboard.
 */
function listarRequisicoes(PDO $pdo): void
{
    $idFarmacia = $_SESSION['id_farmacia'];

    $sql = "SELECT r.id_requisicao, r.nome_paciente, r.setor_origem, r.prioridade,
                   r.status, r.criado_em, r.atendido_em,
                   fo.sigla AS sigla_origem,
                   ua.nome AS nome_atendente
            FROM requisicoes r
            INNER JOIN farmacias fo ON fo.id_farmacia = r.id_farmacia_origem
            LEFT JOIN usuarios ua ON ua.id_usuario = r.id_usuario_atendente
            WHERE r.id_farmacia_destino = :id_farmacia
            ORDER BY
                (r.status = 'Pendente') DESC,
                FIELD(r.prioridade, 'Urgente', 'Alta', 'Rotina'),
                r.criado_em DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute(['id_farmacia' => $idFarmacia]);
    $requisicoes = $stmt->fetchAll();

    // Busca os itens de cada requisição (quantidade pequena de linhas, então é aceitável em loop)
    $sqlItens = "SELECT m.nome, i.quantidade_solicitada
                 FROM itens_requisicao i
                 INNER JOIN medicamentos m ON m.id_medicamento = i.id_medicamento
                 WHERE i.id_requisicao = :id_requisicao";
    $stmtItens = $pdo->prepare($sqlItens);

    foreach ($requisicoes as &$req) {
        $stmtItens->execute(['id_requisicao' => $req['id_requisicao']]);
        $req['itens'] = $stmtItens->fetchAll();
    }

    // Estatísticas para os cards do topo
    $totais = [
        'total'    => count($requisicoes),
        'pendente' => 0,
        'atendido' => 0,
        'urgente'  => 0,
    ];
    foreach ($requisicoes as $req) {
        if ($req['status'] === 'Pendente') {
            $totais['pendente']++;
            if (in_array($req['prioridade'], ['Urgente', 'Alta'])) {
                $totais['urgente']++;
            }
        } elseif ($req['status'] === 'Atendido') {
            $totais['atendido']++;
        }
    }

    echo json_encode([
        'requisicoes' => $requisicoes,
        'estatisticas' => $totais,
    ]);
}

/**
 * Retorna o detalhe completo de uma requisição (usado para abrir o modal).
 */
function detalheRequisicao(PDO $pdo): void
{
    $id = (int)($_GET['id'] ?? 0);

    $sql = "SELECT r.*, fo.sigla AS sigla_origem, ua.nome AS nome_atendente
            FROM requisicoes r
            INNER JOIN farmacias fo ON fo.id_farmacia = r.id_farmacia_origem
            LEFT JOIN usuarios ua ON ua.id_usuario = r.id_usuario_atendente
            WHERE r.id_requisicao = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['id' => $id]);
    $req = $stmt->fetch();

    if (!$req) {
        http_response_code(404);
        echo json_encode(['erro' => 'Requisição não encontrada.']);
        return;
    }

    $sqlItens = "SELECT i.id_item, i.id_medicamento, m.nome, i.quantidade_solicitada,
                        i.quantidade_atendida, i.numero_lote_dispensado
                 FROM itens_requisicao i
                 INNER JOIN medicamentos m ON m.id_medicamento = i.id_medicamento
                 WHERE i.id_requisicao = :id";
    $stmtItens = $pdo->prepare($sqlItens);
    $stmtItens->execute(['id' => $id]);
    $req['itens'] = $stmtItens->fetchAll();

    echo json_encode($req);
}

/**
 * Marca a requisição como Atendido, grava o lote de cada item
 * e registra quem atendeu (usuário da sessão).
 * Espera receber via POST: id_requisicao e itens[] = [{id_item, lote}]
 */
function dispensarRequisicao(PDO $pdo): void
{
    $dados = json_decode(file_get_contents('php://input'), true);
    $idRequisicao = (int)($dados['id_requisicao'] ?? 0);
    $itens = $dados['itens'] ?? [];

    if (!$idRequisicao || empty($itens)) {
        http_response_code(400);
        echo json_encode(['erro' => 'Dados incompletos para dispensar.']);
        return;
    }

    try {
        $pdo->beginTransaction();

        $stmtItem = $pdo->prepare(
            "UPDATE itens_requisicao
             SET numero_lote_dispensado = :lote, quantidade_atendida = quantidade_solicitada
             WHERE id_item = :id_item AND id_requisicao = :id_requisicao"
        );

        foreach ($itens as $item) {
            $lote = trim($item['lote'] ?? '');
            if ($lote === '') {
                throw new Exception('Todos os itens precisam de um número de lote.');
            }
            $stmtItem->execute([
                'lote' => $lote,
                'id_item' => (int)$item['id_item'],
                'id_requisicao' => $idRequisicao,
            ]);
        }

        $stmtReq = $pdo->prepare(
            "UPDATE requisicoes
             SET status = 'Atendido', id_usuario_atendente = :id_usuario, atendido_em = NOW()
             WHERE id_requisicao = :id_requisicao"
        );
        $stmtReq->execute([
            'id_usuario' => $_SESSION['id_usuario'],
            'id_requisicao' => $idRequisicao,
        ]);

        $pdo->commit();
        echo json_encode(['sucesso' => true]);
    } catch (Exception $e) {
        $pdo->rollBack();
        http_response_code(400);
        echo json_encode(['erro' => $e->getMessage()]);
    }
}

/**
 * Cancela uma requisição pendente.
 */
function cancelarRequisicao(PDO $pdo): void
{
    $dados = json_decode(file_get_contents('php://input'), true);
    $idRequisicao = (int)($dados['id_requisicao'] ?? 0);

    $stmt = $pdo->prepare(
        "UPDATE requisicoes
         SET status = 'Cancelado', id_usuario_atendente = :id_usuario, atendido_em = NOW()
         WHERE id_requisicao = :id_requisicao AND status = 'Pendente'"
    );
    $stmt->execute([
        'id_usuario' => $_SESSION['id_usuario'],
        'id_requisicao' => $idRequisicao,
    ]);

    echo json_encode(['sucesso' => $stmt->rowCount() > 0]);
}

/**
 * Cria uma nova requisição com seus itens.
 * Espera JSON: { id_farmacia_destino, nome_paciente, setor_origem, prioridade, itens: [{nome_medicamento, quantidade}] }
 * Observação: se o medicamento digitado não existir no catálogo, ele é criado automaticamente.
 */
function criarRequisicao(PDO $pdo): void
{
    $dados = json_decode(file_get_contents('php://input'), true);

    $idFarmaciaDestino = (int)($dados['id_farmacia_destino'] ?? 0);
    $nomePaciente = trim($dados['nome_paciente'] ?? '');
    $setorOrigem = trim($dados['setor_origem'] ?? '');
    $prioridade = $dados['prioridade'] ?? 'Rotina';
    $itens = $dados['itens'] ?? [];

    if (!$idFarmaciaDestino || empty($itens)) {
        http_response_code(400);
        echo json_encode(['erro' => 'Selecione a farmácia destino e ao menos um item.']);
        return;
    }

    try {
        $pdo->beginTransaction();

        $stmtReq = $pdo->prepare(
            "INSERT INTO requisicoes
                (id_farmacia_origem, id_farmacia_destino, id_usuario_solicitante,
                 nome_paciente, setor_origem, prioridade, status)
             VALUES (:origem, :destino, :usuario, :paciente, :setor, :prioridade, 'Pendente')"
        );
        $stmtReq->execute([
            'origem' => $_SESSION['id_farmacia'],
            'destino' => $idFarmaciaDestino,
            'usuario' => $_SESSION['id_usuario'],
            'paciente' => $nomePaciente !== '' ? $nomePaciente : null,
            'setor' => $setorOrigem !== '' ? $setorOrigem : null,
            'prioridade' => $prioridade,
        ]);
        $idRequisicao = $pdo->lastInsertId();

        $stmtBuscaMed = $pdo->prepare("SELECT id_medicamento FROM medicamentos WHERE nome = :nome LIMIT 1");
        $stmtCriaMed = $pdo->prepare("INSERT INTO medicamentos (nome, unidade_medida) VALUES (:nome, 'unidade')");
        $stmtItem = $pdo->prepare(
            "INSERT INTO itens_requisicao (id_requisicao, id_medicamento, quantidade_solicitada)
             VALUES (:id_requisicao, :id_medicamento, :quantidade)"
        );

        foreach ($itens as $item) {
            $nomeMed = trim($item['nome_medicamento'] ?? '');
            $quantidade = (int)($item['quantidade'] ?? 1);
            if ($nomeMed === '') {
                continue;
            }

            $stmtBuscaMed->execute(['nome' => $nomeMed]);
            $medicamento = $stmtBuscaMed->fetch();

            if ($medicamento) {
                $idMedicamento = $medicamento['id_medicamento'];
            } else {
                $stmtCriaMed->execute(['nome' => $nomeMed]);
                $idMedicamento = $pdo->lastInsertId();
            }

            $stmtItem->execute([
                'id_requisicao' => $idRequisicao,
                'id_medicamento' => $idMedicamento,
                'quantidade' => $quantidade,
            ]);
        }

        $pdo->commit();
        echo json_encode(['sucesso' => true, 'id_requisicao' => $idRequisicao]);
    } catch (Exception $e) {
        $pdo->rollBack();
        http_response_code(400);
        echo json_encode(['erro' => $e->getMessage()]);
    }
}
