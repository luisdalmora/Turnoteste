<?php
// gerenciar_ausencias.php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/conexao.php'; // MySQLi
require_once __DIR__ . '/LogHelper.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$logger = new LogHelper($conexao);
header('Content-Type: application/json');

function formatarDataParaBanco($dataStr) {
    if (empty($dataStr)) return null;
    try {
        $dt = new DateTime($dataStr);
        return $dt->format('Y-m-d');
    } catch (Exception $e) {
        return null;
    }
}

$novoCsrfTokenParaCliente = null;
$csrfTokenSessionKey = 'csrf_token_ausencias';

function fecharConexaoETransmitir($conexaoMysqli, $jsonData) {
    if (isset($conexaoMysqli) && $conexaoMysqli instanceof \mysqli) {
        mysqli_close($conexaoMysqli);
    }
    echo json_encode($jsonData);
    exit;
}

$userIdForLog = $_SESSION['usuario_id'] ?? 'N/A_PRE_AUTH';

// ... (Bloco de validação de sessão e CSRF como antes) ...
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
        $logger->log('SECURITY_WARNING', 'Tentativa de POST não autenticada em gerenciar_ausencias.', ['user_id' => $userIdForLog]);
        fecharConexaoETransmitir($conexao, ['success' => false, 'message' => 'Acesso negado. Sessão inválida.']);
    }
    $userIdForLog = $_SESSION['usuario_id']; 

    $input = json_decode(file_get_contents('php://input'), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        $logger->log('ERROR', 'JSON de entrada inválido (POST ausências).', ['user_id' => $userIdForLog, 'json_error' => json_last_error_msg()]);
        fecharConexaoETransmitir($conexao, ['success' => false, 'message' => 'Requisição inválida (JSON).']);
    }
    if (!isset($input['csrf_token']) || !isset($_SESSION[$csrfTokenSessionKey]) || !hash_equals($_SESSION[$csrfTokenSessionKey], $input['csrf_token'])) {
        $logger->log('SECURITY_WARNING', 'Falha na validação do CSRF token (POST ausências).', ['user_id' => $userIdForLog, 'acao' => $input['acao'] ?? 'desconhecida']);
        fecharConexaoETransmitir($conexao, ['success' => false, 'message' => 'Erro de segurança. Por favor, recarregue a página e tente novamente.']);
    }
    $_SESSION[$csrfTokenSessionKey] = bin2hex(random_bytes(32));
    $novoCsrfTokenParaCliente = $_SESSION[$csrfTokenSessionKey];

} else if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
        $logger->log('SECURITY_WARNING', 'Tentativa de GET não autenticada em gerenciar_ausencias.', ['user_id' => $userIdForLog]);
        fecharConexaoETransmitir($conexao, ['success' => false, 'message' => 'Acesso negado.']);
    }
     $userIdForLog = $_SESSION['usuario_id']; 

    if (empty($_SESSION[$csrfTokenSessionKey])) {
        $_SESSION[$csrfTokenSessionKey] = bin2hex(random_bytes(32));
    }
    $novoCsrfTokenParaCliente = $_SESSION[$csrfTokenSessionKey];
} else {
    http_response_code(405); 
    $logger->log('WARNING', 'Método HTTP não suportado em gerenciar_ausencias.', ['method' => $_SERVER['REQUEST_METHOD'] ?? 'N/A']);
    fecharConexaoETransmitir($conexao, ['success' => false, 'message' => 'Método não suportado.']);
}
$userId = $_SESSION['usuario_id'];


if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $anoFiltro = filter_input(INPUT_GET, 'ano', FILTER_VALIDATE_INT);
    $mesFiltro = filter_input(INPUT_GET, 'mes', FILTER_VALIDATE_INT);

    if ($anoFiltro === false || $mesFiltro === false || $mesFiltro < 1 || $mesFiltro > 12) {
        // ... (log e erro como antes) ...
        $logger->log('WARNING', 'Parâmetros de ano/mês inválidos para GET ausências.', ['user_id' => $userId, 'ano' => $_GET['ano'] ?? 'N/A', 'mes' => $_GET['mes'] ?? 'N/A']);
        fecharConexaoETransmitir($conexao, ['success' => false, 'message' => 'Parâmetros de ano/mês inválidos.', 'csrf_token' => $novoCsrfTokenParaCliente]);
    }
    
    $primeiroDiaMesFiltro = sprintf('%04d-%02d-01', $anoFiltro, $mesFiltro);
    $ultimoDiaMesFiltro = date('Y-m-t', strtotime($primeiroDiaMesFiltro));

    // ADICIONADO colaborador_nome AO SELECT
    $sql = "SELECT `id`, `data_inicio`, `data_fim`, `colaborador_nome`, `observacoes` 
            FROM `ausencias`
            WHERE `criado_por_usuario_id` = ? 
            AND (
                 (`data_inicio` BETWEEN ? AND ?) OR 
                 (`data_fim` BETWEEN ? AND ?) OR     
                 (`data_inicio` < ? AND `data_fim` > ?) 
            )
            ORDER BY `data_inicio` ASC";

    $stmt = mysqli_prepare($conexao, $sql);
    // ... (verificação do $stmt como antes) ...
    if ($stmt === false) {
        $logger->log('ERROR', 'Erro ao preparar consulta GET ausências (MySQLi).', ['mysql_error' => mysqli_error($conexao), 'user_id' => $userId, 'sql' => $sql]);
        fecharConexaoETransmitir($conexao, ['success' => false, 'message' => 'Erro ao preparar consulta de ausências.', 'csrf_token' => $novoCsrfTokenParaCliente]);
    }

    mysqli_stmt_bind_param($stmt, "issssss", 
        $userId, 
        $primeiroDiaMesFiltro, $ultimoDiaMesFiltro, 
        $primeiroDiaMesFiltro, $ultimoDiaMesFiltro, 
        $primeiroDiaMesFiltro, $ultimoDiaMesFiltro  
    );

    // ... (execução e fetch como antes) ...
    if (!mysqli_stmt_execute($stmt)) {
        $logger->log('ERROR', 'Erro ao executar consulta GET ausências (MySQLi).', ['mysql_stmt_error' => mysqli_stmt_error($stmt), 'user_id' => $userId]);
        mysqli_stmt_close($stmt);
        fecharConexaoETransmitir($conexao, ['success' => false, 'message' => 'Erro ao executar consulta de ausências.', 'csrf_token' => $novoCsrfTokenParaCliente]);
    }

    $result = mysqli_stmt_get_result($stmt);
    $itens_carregados = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $itens_carregados[] = $row;
    }
    mysqli_free_result($result);
    mysqli_stmt_close($stmt);

    $logger->log('INFO', 'Consulta de ausências executada.', [
        'user_id' => $userId, 
        'ano' => $anoFiltro, 
        'mes' => $mesFiltro, 
        'num_ausencias_encontradas' => count($itens_carregados),
    ]);

    fecharConexaoETransmitir($conexao, ['success' => true, 'message' => 'Ausências carregadas.', 'data' => $itens_carregados, 'csrf_token' => $novoCsrfTokenParaCliente]);

}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $input['acao'] ?? null;

    if ($acao === 'excluir_ausencias') {
        // ... (lógica de exclusão como antes, não precisa mudar) ...
        $idsParaExcluir = $input['ids_ausencias'] ?? [];
        if (empty($idsParaExcluir)) {
            fecharConexaoETransmitir($conexao, ['success' => false, 'message' => 'Nenhum ID fornecido para exclusão.', 'csrf_token' => $novoCsrfTokenParaCliente]);
        }
        $idsValidos = array_filter(array_map('intval', $idsParaExcluir), fn($id) => $id > 0);
        if (empty($idsValidos)) {
            fecharConexaoETransmitir($conexao, ['success' => false, 'message' => 'IDs de ausência inválidos.', 'csrf_token' => $novoCsrfTokenParaCliente]);
        }

        $placeholders = implode(',', array_fill(0, count($idsValidos), '?'));
        $sql_delete = "DELETE FROM `ausencias` WHERE `id` IN ($placeholders) AND `criado_por_usuario_id` = ?";

        $stmt_delete = mysqli_prepare($conexao, $sql_delete);
        if (!$stmt_delete) {
            $logger->log('ERROR', 'Falha ao preparar exclusão de ausências BD (MySQLi).', ['user_id' => $userId, 'mysql_error' => mysqli_error($conexao)]);
            fecharConexaoETransmitir($conexao, ['success' => false, 'message' => 'Erro ao preparar exclusão de ausências.', 'csrf_token' => $novoCsrfTokenParaCliente]);
        }

        $param_types_delete = str_repeat('i', count($idsValidos)) . 'i';
        $params_bind_delete = array_merge($idsValidos, [$userId]);
        mysqli_stmt_bind_param($stmt_delete, $param_types_delete, ...$params_bind_delete);

        if (mysqli_stmt_execute($stmt_delete)) {
            $numLinhasAfetadas = mysqli_stmt_affected_rows($stmt_delete);
            $logger->log('INFO', "$numLinhasAfetadas ausência(s) excluída(s) do BD.", ['user_id' => $userId, 'ids' => $idsValidos, 'affected_rows' => $numLinhasAfetadas]);
            mysqli_stmt_close($stmt_delete);
            fecharConexaoETransmitir($conexao, ['success' => true, 'message' => "$numLinhasAfetadas ausência(s) excluída(s) com sucesso.", 'csrf_token' => $novoCsrfTokenParaCliente]);
        } else {
            $logger->log('ERROR', 'Falha ao executar exclusão de ausências BD (MySQLi).', ['user_id' => $userId, 'mysql_stmt_error' => mysqli_stmt_error($stmt_delete)]);
            mysqli_stmt_close($stmt_delete);
            fecharConexaoETransmitir($conexao, ['success' => false, 'message' => 'Erro ao excluir ausências do banco de dados.', 'csrf_token' => $novoCsrfTokenParaCliente]);
        }

    } elseif ($acao === 'salvar_ausencias') {
        $dadosItensRecebidos = $input['ausencias'] ?? [];
        // ... (validação de dadosItensRecebidos como antes) ...
        if (empty($dadosItensRecebidos) && !isset($input['ausencias'])) {
            $logger->log('WARNING', 'Nenhum dado de ausência recebido para salvar (chave "ausencias" ausente).', ['user_id' => $userId]);
            fecharConexaoETransmitir($conexao, ['success' => false, 'message' => 'Nenhum dado de ausência recebido.', 'csrf_token' => $novoCsrfTokenParaCliente]);
        }
         if (empty($dadosItensRecebidos) && isset($input['ausencias'])) { 
            $logger->log('INFO', 'Array vazio de ausências recebido para salvar. Nenhuma ação no BD será tomada.', ['user_id' => $userId]);
            fecharConexaoETransmitir($conexao, ['success' => true, 'message' => 'Nenhuma ausência para salvar ou atualizar.', 'csrf_token' => $novoCsrfTokenParaCliente]);
        }

        $errosOperacao = [];
        // ADICIONADO colaborador_nome AO INSERT E UPDATE
        $sql_insert = "INSERT INTO `ausencias` (`data_inicio`, `data_fim`, `colaborador_nome`, `observacoes`, `criado_por_usuario_id`) VALUES (?, ?, ?, ?, ?)";
        $sql_update = "UPDATE `ausencias` SET `data_inicio` = ?, `data_fim` = ?, `colaborador_nome` = ?, `observacoes` = ? WHERE `id` = ? AND `criado_por_usuario_id` = ?";

        $stmt_insert_prepared = mysqli_prepare($conexao, $sql_insert);
        $stmt_update_prepared = mysqli_prepare($conexao, $sql_update);

        // ... (verificação de $stmt_insert_prepared e $stmt_update_prepared como antes) ...
        if (!$stmt_insert_prepared || !$stmt_update_prepared) {
            $logger->log('ERROR', 'Falha ao preparar statements de INSERT/UPDATE para ausências (MySQLi).', ['mysql_error' => mysqli_error($conexao), 'user_id' => $userId]);
            if ($stmt_insert_prepared) mysqli_stmt_close($stmt_insert_prepared);
            if ($stmt_update_prepared) mysqli_stmt_close($stmt_update_prepared);
            fecharConexaoETransmitir($conexao, ['success' => false, 'message' => 'Erro ao preparar operações de salvamento.', 'csrf_token' => $novoCsrfTokenParaCliente]);
        }


        foreach ($dadosItensRecebidos as $item) {
            $itemId = $item['id'] ?? null;
            $dataInicioStr = $item['data_inicio'] ?? null;
            $dataFimStr = $item['data_fim'] ?? null;
            $colaboradorNome = isset($item['colaborador_nome']) ? trim($item['colaborador_nome']) : null; // NOVO CAMPO
            if($colaboradorNome === '') $colaboradorNome = null; // Trata string vazia como NULL
            $observacoes = isset($item['observacoes']) ? trim($item['observacoes']) : null;
            if ($observacoes === '') $observacoes = null; 

            $dataInicioDb = formatarDataParaBanco($dataInicioStr);
            $dataFimDb = formatarDataParaBanco($dataFimStr);

            // ... (validação de datas e datas fim < inicio como antes) ...
            if (!$dataInicioDb || !$dataFimDb) {
                $errosOperacao[] = "Ausência com datas incompletas/inválidas (item: " . ($colaboradorNome ?: ($observacoes ?: ($itemId ?: 'Novo'))) . "). Data Início: '{$dataInicioStr}', Data Fim: '{$dataFimStr}'."; continue;
            }
            if (strtotime($dataFimDb) < strtotime($dataInicioDb)) {
                $errosOperacao[] = "Data Fim ('{$dataFimStr}') não pode ser anterior à Data Início ('{$dataInicioStr}') para ausência de '{$colaboradorNome}'."; continue;
            }

            $isUpdate = ($itemId && substr((string)$itemId, 0, 4) !== "new-");

            if ($isUpdate) {
                $itemIdRealDb = (int)$itemId;
                // Tipos: s, s, s, s, i, i (data_inicio, data_fim, colaborador_nome, observacoes, id, userId)
                mysqli_stmt_bind_param($stmt_update_prepared, "ssssii", $dataInicioDb, $dataFimDb, $colaboradorNome, $observacoes, $itemIdRealDb, $userId);
                if (!mysqli_stmt_execute($stmt_update_prepared)) {
                    $errosOperacao[] = "Erro ao ATUALIZAR ausência ID {$itemIdRealDb}: " . mysqli_stmt_error($stmt_update_prepared);
                }
            } else {
                // Tipos: s, s, s, s, i (data_inicio, data_fim, colaborador_nome, observacoes, userId)
                mysqli_stmt_bind_param($stmt_insert_prepared, "ssssi", $dataInicioDb, $dataFimDb, $colaboradorNome, $observacoes, $userId);
                if (!mysqli_stmt_execute($stmt_insert_prepared)) {
                    $errosOperacao[] = "Erro ao INSERIR ausência para '{$colaboradorNome}': " . mysqli_stmt_error($stmt_insert_prepared);
                }
            }
        }
        // ... (fechamento de statements e tratamento de erros/sucesso como antes) ...
        if ($stmt_insert_prepared) mysqli_stmt_close($stmt_insert_prepared);
        if ($stmt_update_prepared) mysqli_stmt_close($stmt_update_prepared);

        if (!empty($errosOperacao)) {
            $logger->log('WARNING', 'Erros ao salvar ausências.', ['user_id' => $userId, 'errors' => $errosOperacao]);
            fecharConexaoETransmitir($conexao, ['success' => false, 'message' => 'Ocorreram erros: ' . implode("; ", $errosOperacao), 'csrf_token' => $novoCsrfTokenParaCliente]);
        } else {
            $logger->log('INFO', 'Ausências salvas com sucesso.', ['user_id' => $userId, 'num_items' => count($dadosItensRecebidos)]);
            fecharConexaoETransmitir($conexao, ['success' => true, 'message' => 'Ausências salvas com sucesso!', 'csrf_token' => $novoCsrfTokenParaCliente]);
        }
    } else {
        // ... (Ação desconhecida como antes) ...
        $logger->log('WARNING', 'Ação POST desconhecida em gerenciar ausências.', ['acao' => $acao, 'user_id' => $userId]);
        fecharConexaoETransmitir($conexao, ['success' => false, 'message' => 'Ação desconhecida.', 'csrf_token' => $novoCsrfTokenParaCliente]);
    }
}

// ... (Fallback como antes) ...
$logger->log('CRITICAL', 'Fluxo inesperado alcançou o fim de gerenciar_ausencias.php.', ['user_id' => $userIdForLog, 'method' => $_SERVER['REQUEST_METHOD'] ?? 'N/A']);
fecharConexaoETransmitir($conexao, ['success' => false, 'message' => 'Erro interno no servidor.', 'csrf_token' => $novoCsrfTokenParaCliente ?? bin2hex(random_bytes(32))]);
