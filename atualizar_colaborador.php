<?php
// atualizar_colaborador.php

// NOTA: Certifique-se de que LogHelper.php está adaptado para MySQLi se ele interage com o BD
// e que conexao.php agora estabelece uma conexão MySQLi.

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/conexao.php'; // DEVE estar configurado para MySQLi
require_once __DIR__ . '/LogHelper.php';

// Inicia a sessão se ainda não estiver iniciada
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$logger = new LogHelper($conexao); // $conexao agora é um objeto/recurso MySQLi
header('Content-Type: application/json');

// --- Verificação de Sessão e CSRF Token ---
if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Acesso negado. Sessão inválida.']); exit;
}
$userIdLogado = $_SESSION['usuario_id']; // Para auditoria

$input = json_decode(file_get_contents('php://input'), true);
if (json_last_error() !== JSON_ERROR_NONE) {
    $logger->log('ERROR', 'JSON de entrada inválido em atualizar_colaborador.', ['user_id' => $userIdLogado, 'json_error' => json_last_error_msg()]);
    echo json_encode(['success' => false, 'message' => 'Requisição inválida (JSON).']); exit;
}

if (!isset($input['csrf_token']) || !isset($_SESSION['csrf_token_colab_manage']) || !hash_equals($_SESSION['csrf_token_colab_manage'], $input['csrf_token'])) {
    $logger->log('SECURITY_WARNING', 'Falha CSRF token em atualizar_colaborador.', ['user_id' => $userIdLogado]);
    echo json_encode(['success' => false, 'message' => 'Erro de segurança. Recarregue a página.']); exit;
}

// Regenera o token CSRF após o uso bem-sucedido
$_SESSION['csrf_token_colab_manage'] = bin2hex(random_bytes(32));
$novoCsrfToken = $_SESSION['csrf_token_colab_manage'];

// --- Obter e Validar Dados ---
$colab_id = isset($input['colab_id']) ? (int)$input['colab_id'] : 0;
$nome_completo = isset($input['nome_completo']) ? trim($input['nome_completo']) : '';
$email = isset($input['email']) ? trim($input['email']) : null; // Mantém null se não fornecido
$cargo = isset($input['cargo']) ? trim($input['cargo']) : null; // Mantém null se não fornecido

if ($colab_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID do colaborador inválido.', 'csrf_token' => $novoCsrfToken]); exit;
}
if (empty($nome_completo)) {
    echo json_encode(['success' => false, 'message' => 'Nome completo é obrigatório.', 'csrf_token' => $novoCsrfToken]); exit;
}

// Se email foi fornecido e não é vazio, valide-o
if ($email !== null && $email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Formato de e-mail inválido.', 'csrf_token' => $novoCsrfToken]); exit;
}

// Converter strings vazias para NULL para colunas que permitem nulos
if (is_string($email) && trim($email) === '') {
    $email = null;
}
if (is_string($cargo) && trim($cargo) === '') {
    $cargo = null;
}

// --- Atualizar no Banco de Dados (MySQLi) ---
$sql = "UPDATE `colaboradores` SET `nome_completo` = ?, `email` = ?, `cargo` = ? WHERE `id` = ?";

$stmt = mysqli_prepare($conexao, $sql);

if ($stmt) {
    // Vincular parâmetros: s = string, i = integer.
    // nome_completo (s), email (s), cargo (s), id (i) -> "sssi"
    mysqli_stmt_bind_param($stmt, "sssi", $nome_completo, $email, $cargo, $colab_id);

    if (mysqli_stmt_execute($stmt)) {
        $rows_affected = mysqli_stmt_affected_rows($stmt);
        if ($rows_affected > 0) {
            $logger->log('INFO', 'Colaborador atualizado com sucesso.', ['colab_id' => $colab_id, 'admin_user_id' => $userIdLogado]);
            echo json_encode(['success' => true, 'message' => 'Colaborador atualizado com sucesso!', 'csrf_token' => $novoCsrfToken]);
        } elseif ($rows_affected === 0) {
            echo json_encode(['success' => true, 'message' => 'Nenhuma alteração detectada ou colaborador não encontrado.', 'csrf_token' => $novoCsrfToken]);
        } else { // $rows_affected === -1 (erro)
            $error_msg = mysqli_stmt_error($stmt);
            $logger->log('ERROR', 'Erro ao obter linhas afetadas para atualizar colaborador (mysqli_stmt_affected_rows retornou < 0).', ['colab_id' => $colab_id, 'error' => $error_msg, 'admin_user_id' => $userIdLogado]);
            echo json_encode(['success' => false, 'message' => 'Erro ao verificar a atualização do colaborador.', 'csrf_token' => $novoCsrfToken]);
        }
    } else {
        $error_msg = mysqli_stmt_error($stmt);
        $error_code = mysqli_stmt_errno($stmt); // Para MySQLi, use mysqli_stmt_errno com o statement
        
        $logger->log('ERROR', 'Erro ao executar atualização de colaborador.', ['colab_id' => $colab_id, 'mysql_errno' => $error_code, 'mysql_error' => $error_msg, 'admin_user_id' => $userIdLogado]);
        
        $user_message = "Erro ao atualizar o colaborador.";
        // Código de erro do MySQL para violação de chave única (UNIQUE constraint) é 1062
        if ($error_code == 1062) {
            // Você pode verificar $error_msg para ver qual constraint foi violada se tiver múltiplas.
            // Ex: if (strpos(strtolower($error_msg), 'email') !== false) ...
            $user_message = "Erro: O e-mail informado já existe para outro colaborador.";
        }
        echo json_encode(['success' => false, 'message' => $user_message, 'csrf_token' => $novoCsrfToken]);
    }
    mysqli_stmt_close($stmt);
} else {
    $error_msg = mysqli_error($conexao); // Erro na preparação do statement
    $logger->log('ERROR', 'Erro ao preparar statement para atualizar colaborador.', ['mysql_error' => $error_msg, 'admin_user_id' => $userIdLogado]);
    echo json_encode(['success' => false, 'message' => 'Erro no sistema ao tentar preparar a atualização.', 'csrf_token' => $novoCsrfToken]);
}

if ($conexao) {
    mysqli_close($conexao);
}
