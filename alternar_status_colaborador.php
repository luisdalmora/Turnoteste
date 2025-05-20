<?php
// alternar_status_colaborador.php

// NOTA: Certifique-se de que LogHelper.php está adaptado para MySQLi se ele interage com o BD
// e que conexao.php agora estabelece uma conexão MySQLi.

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/conexao.php'; // DEVE estar configurado para MySQLi (ex: usando mysqli_connect)
require_once __DIR__ . '/LogHelper.php';

// Inicia a sessão se ainda não estiver iniciada (boa prática)
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Assumindo que LogHelper foi adaptado ou não usa funções de banco de dados diretamente
// de uma maneira que dependa do driver sqlsrv.
// Se LogHelper usa $conexao para executar queries, ele também precisa ser atualizado para mysqli.
$logger = new LogHelper($conexao);
header('Content-Type: application/json');

// --- Verificação de Sessão e CSRF Token ---
if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Acesso negado. Sessão inválida.']); exit;
}
$userIdLogado = $_SESSION['usuario_id'];

$input = json_decode(file_get_contents('php://input'), true);
if (json_last_error() !== JSON_ERROR_NONE) {
    // Não há $conexao para LogHelper aqui se o JSON falhar antes da conexão ser usada criticamente,
    // mas se LogHelper for instanciado antes, ok.
    // Se $conexao pode não estar disponível, LogHelper precisa lidar com isso ou logar para arquivo.
    $logger->log('ERROR', 'JSON de entrada inválido em alternar_status_colaborador.', ['user_id' => $userIdLogado, 'json_error' => json_last_error_msg()]);
    echo json_encode(['success' => false, 'message' => 'Requisição inválida (JSON).']); exit;
}

if (!isset($input['csrf_token']) || !isset($_SESSION['csrf_token_colab_manage']) || !hash_equals($_SESSION['csrf_token_colab_manage'], $input['csrf_token'])) {
    $logger->log('SECURITY_WARNING', 'Falha CSRF token em alternar_status_colaborador.', ['user_id' => $userIdLogado]);
    echo json_encode(['success' => false, 'message' => 'Erro de segurança. Recarregue a página.']); exit;
}

// Regenera o token CSRF após o uso bem-sucedido
$_SESSION['csrf_token_colab_manage'] = bin2hex(random_bytes(32));
$novoCsrfToken = $_SESSION['csrf_token_colab_manage'];

// --- Obter e Validar Dados ---
$colab_id = isset($input['colab_id']) ? (int)$input['colab_id'] : 0;
$novo_status = isset($input['novo_status']) ? (int)$input['novo_status'] : null;


if ($colab_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID do colaborador inválido.', 'csrf_token' => $novoCsrfToken]); exit;
}
if ($novo_status === null || !in_array($novo_status, [0, 1])) {
    echo json_encode(['success' => false, 'message' => 'Novo status inválido. Deve ser 0 ou 1.', 'csrf_token' => $novoCsrfToken]); exit;
}

// --- Atualizar Status no Banco de Dados (MySQLi) ---
$sql = "UPDATE `colaboradores` SET `ativo` = ? WHERE `id` = ?";

// Preparar statement com MySQLi
$stmt = mysqli_prepare($conexao, $sql);

if ($stmt) {
    // Vincular parâmetros. "ii" significa que ambos os parâmetros são inteiros.
    mysqli_stmt_bind_param($stmt, "ii", $novo_status, $colab_id);

    if (mysqli_stmt_execute($stmt)) {
        $rows_affected = mysqli_stmt_affected_rows($stmt);
        if ($rows_affected > 0) {
            $status_texto = $novo_status == 1 ? "ativado" : "desativado";
            $logger->log('INFO', "Status do colaborador ID {$colab_id} alterado para {$status_texto}.", ['admin_user_id' => $userIdLogado]);
            echo json_encode(['success' => true, 'message' => "Colaborador {$status_texto} com sucesso!", 'novo_status_bool' => (bool)$novo_status, 'csrf_token' => $novoCsrfToken]);
        } elseif ($rows_affected === 0) {
            // Nenhuma linha afetada pode significar que o colaborador não foi encontrado
            // ou que o status já era o desejado.
            echo json_encode(['success' => true, 'message' => 'Nenhuma alteração de status necessária (colaborador não encontrado ou status já definido).', 'csrf_token' => $novoCsrfToken]);
        } else { // $rows_affected === -1 indica um erro na execução da query após o execute bem sucedido (raro, mas possível)
            $error_msg = mysqli_stmt_error($stmt);
            $logger->log('ERROR', 'Erro ao obter linhas afetadas após atualização de status do colaborador (mysqli_stmt_affected_rows retornou < 0).', ['colab_id' => $colab_id, 'error' => $error_msg, 'admin_user_id' => $userIdLogado]);
            echo json_encode(['success' => false, 'message' => 'Erro ao verificar a atualização do status do colaborador.', 'csrf_token' => $novoCsrfToken]);
        }
    } else {
        $error_msg = mysqli_stmt_error($stmt);
        $logger->log('ERROR', 'Erro ao executar atualização de status do colaborador.', ['colab_id' => $colab_id, 'error' => $error_msg, 'admin_user_id' => $userIdLogado]);
        echo json_encode(['success' => false, 'message' => 'Erro ao atualizar o status do colaborador.', 'csrf_token' => $novoCsrfToken]);
    }
    mysqli_stmt_close($stmt);
} else {
    $error_msg = mysqli_error($conexao); // Erro ao preparar a statement
    $logger->log('ERROR', 'Erro ao preparar statement para alternar status.', ['error' => $error_msg, 'admin_user_id' => $userIdLogado]);
    echo json_encode(['success' => false, 'message' => 'Erro no sistema ao tentar preparar la alteração de status.', 'csrf_token' => $novoCsrfToken]);
}

if ($conexao) {
    mysqli_close($conexao);
}
