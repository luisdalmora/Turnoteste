<?php
// gerenciar_observacao_geral.php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/conexao.php'; // Agora $conexao é um objeto MySQLi
require_once __DIR__ . '/LogHelper.php';

// Assegura que a sessão foi iniciada (config.php já deve fazer isso)
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$logger = new LogHelper($conexao); // $conexao agora é MySQLi
header('Content-Type: application/json');

$settingKey = 'observacoes_gerais';
$novoCsrfTokenParaCliente = null;
$csrfTokenSessionKey = 'csrf_token_obs_geral';

// Função auxiliar para fechar conexão e sair
function fecharConexaoObsESair($conexaoMysqli, $jsonData) {
    if (isset($conexaoMysqli) && $conexaoMysqli) {
        mysqli_close($conexaoMysqli);
    }
    echo json_encode($jsonData);
    exit;
}

// --- Verificação de Sessão e CSRF Token ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
        fecharConexaoObsESair($conexao, ['success' => false, 'message' => 'Acesso negado. Sessão inválida.']);
    }
    $input = json_decode(file_get_contents('php://input'), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        $logger->log('ERROR', 'JSON de entrada inválido (CSRF check obs geral).', ['user_id' => $_SESSION['usuario_id'] ?? null, 'json_error' => json_last_error_msg()]);
        fecharConexaoObsESair($conexao, ['success' => false, 'message' => 'Requisição inválida (JSON).']);
    }
    if (!isset($input['csrf_token']) || !isset($_SESSION[$csrfTokenSessionKey]) || !hash_equals($_SESSION[$csrfTokenSessionKey], $input['csrf_token'])) {
        $logger->log('SECURITY_WARNING', 'Falha na validação do CSRF token (obs geral).', ['user_id' => $_SESSION['usuario_id'] ?? null]);
        fecharConexaoObsESair($conexao, ['success' => false, 'message' => 'Erro de segurança. Recarregue a página.']);
    }
    $_SESSION[$csrfTokenSessionKey] = bin2hex(random_bytes(32));
    $novoCsrfTokenParaCliente = $_SESSION[$csrfTokenSessionKey];

} else if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
        fecharConexaoObsESair($conexao, ['success' => false, 'message' => 'Acesso negado.']);
    }
    if (empty($_SESSION[$csrfTokenSessionKey])) { $_SESSION[$csrfTokenSessionKey] = bin2hex(random_bytes(32)); }
    $novoCsrfTokenParaCliente = $_SESSION[$csrfTokenSessionKey];
} else {
    http_response_code(405);
    fecharConexaoObsESair($conexao, ['success' => false, 'message' => 'Método não suportado.']);
}
$userId = $_SESSION['usuario_id'];


if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $sql = "SELECT `setting_value` FROM `system_settings` WHERE `setting_key` = ?";
    
    $stmt = mysqli_prepare($conexao, $sql);
    if (!$stmt) {
        $logger->log('ERROR', 'Falha ao preparar SELECT para observação geral (MySQLi).', ['user_id' => $userId, 'setting_key' => $settingKey, 'mysql_error' => mysqli_error($conexao)]);
        fecharConexaoObsESair($conexao, ['success' => false, 'message' => 'Erro ao preparar busca da observação.', 'csrf_token' => $novoCsrfTokenParaCliente]);
    }

    mysqli_stmt_bind_param($stmt, "s", $settingKey); // s para string

    if (mysqli_stmt_execute($stmt)) {
        $result = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($result);
        mysqli_free_result($result);
        $observacao = $row ? $row['setting_value'] : '';
        mysqli_stmt_close($stmt);
        fecharConexaoObsESair($conexao, ['success' => true, 'observacao' => $observacao, 'csrf_token' => $novoCsrfTokenParaCliente]);
    } else {
        $logger->log('ERROR', 'Falha ao executar SELECT para observação geral (MySQLi).', ['user_id' => $userId, 'setting_key' => $settingKey, 'mysql_stmt_error' => mysqli_stmt_error($stmt)]);
        mysqli_stmt_close($stmt);
        fecharConexaoObsESair($conexao, ['success' => false, 'message' => 'Erro ao buscar observação.', 'csrf_token' => $novoCsrfTokenParaCliente]);
    }

} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $observacaoConteudo = $input['observacao'] ?? '';

    // MySQL "upsert" usando INSERT ... ON DUPLICATE KEY UPDATE
    // Requer que `setting_key` seja uma PRIMARY KEY ou tenha um índice UNIQUE.
    $sql_upsert = "
        INSERT INTO `system_settings` (`setting_key`, `setting_value`) 
        VALUES (?, ?)
        ON DUPLICATE KEY UPDATE `setting_value` = VALUES(`setting_value`);
    ";
    // VALUES(`setting_value`) refere-se ao valor que seria inserido.

    $stmt_upsert = mysqli_prepare($conexao, $sql_upsert);

    if ($stmt_upsert) {
        mysqli_stmt_bind_param($stmt_upsert, "ss", $settingKey, $observacaoConteudo); // s, s para strings

        if (mysqli_stmt_execute($stmt_upsert)) {
            // mysqli_stmt_affected_rows retorna:
            // 1 para INSERT bem-sucedido.
            // 2 para UPDATE bem-sucedido (se o valor mudou).
            // 0 se um UPDATE ocorreu mas o valor antigo era igual ao novo.
            // -1 em erro.
            $affected_rows = mysqli_stmt_affected_rows($stmt_upsert);
            if ($affected_rows >= 0) {
                 $logger->log('INFO', 'Observação geral salva (MySQLi).', ['user_id' => $userId, 'setting_key' => $settingKey, 'affected_rows' => $affected_rows]);
                 mysqli_stmt_close($stmt_upsert);
                 fecharConexaoObsESair($conexao, ['success' => true, 'message' => 'Observação geral salva com sucesso!', 'csrf_token' => $novoCsrfTokenParaCliente]);
            } else {
                 $logger->log('ERROR', 'Observação geral salva (MySQLi), mas affected_rows foi -1.', ['user_id' => $userId, 'setting_key' => $settingKey, 'mysql_stmt_error' => mysqli_stmt_error($stmt_upsert)]);
                 mysqli_stmt_close($stmt_upsert);
                 fecharConexaoObsESair($conexao, ['success' => false, 'message' => 'Erro ao verificar o salvamento da observação.', 'csrf_token' => $novoCsrfTokenParaCliente]);
            }
        } else {
            $logger->log('ERROR', 'Falha ao salvar observação geral (MySQLi execute).', ['user_id' => $userId, 'mysql_stmt_error' => mysqli_stmt_error($stmt_upsert)]);
            mysqli_stmt_close($stmt_upsert);
            fecharConexaoObsESair($conexao, ['success' => false, 'message' => 'Erro ao salvar observação geral.', 'csrf_token' => $novoCsrfTokenParaCliente]);
        }
    } else {
        $logger->log('ERROR', 'Falha ao preparar UPSERT para observação geral (MySQLi).', ['user_id' => $userId, 'mysql_error' => mysqli_error($conexao)]);
        fecharConexaoObsESair($conexao, ['success' => false, 'message' => 'Erro no sistema ao tentar salvar observação.', 'csrf_token' => $novoCsrfTokenParaCliente]);
    }
}

// Fallback para fechar conexão, embora as funções de saída já devam ter feito.
if (isset($conexao) && $conexao) {
    mysqli_close($conexao);
}
