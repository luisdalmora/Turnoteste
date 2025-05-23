<?php
// salvar_turnos.php (Google Calendar Sync Removido)

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/conexao.php'; // MySQLi
require_once __DIR__ . '/LogHelper.php'; // Adaptado para MySQLi
require_once __DIR__ . '/GoogleCalendarHelper.php'; // GoogleCalendarHelper simplificado

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$logger = new LogHelper($conexao);

header('Content-Type: application/json');

// --- Funções Utilitárias ---
function formatarDataParaBanco($dataStr, $anoReferencia) {
    if (empty($dataStr)) return null;
    $partes = explode('/', $dataStr);
    if (count($partes) < 2) return null;
    $dia = trim($partes[0]);
    $mesInput = trim($partes[1]);
    $ano = $anoReferencia;
    if (isset($partes[2])) {
        $anoInputString = trim($partes[2]);
        if (strlen($anoInputString) === 4 && ctype_digit($anoInputString)) {
            $ano = $anoInputString;
        } elseif (strlen($anoInputString) === 2 && ctype_digit($anoInputString)) {
            $ano = "20" . $anoInputString;
        }
    }
    $diaNum = ctype_digit($dia) ? sprintf('%02d', (int)$dia) : null;
    $mesNum = null;
    if (ctype_digit($mesInput)) {
        $mesNum = sprintf('%02d', (int)$mesInput);
    } else {
        $mapaMeses = ['jan'=>'01','fev'=>'02','mar'=>'03','abr'=>'04','mai'=>'05','jun'=>'06','jul'=>'07','ago'=>'08','set'=>'09','out'=>'10','nov'=>'11','dez'=>'12'];
        $mesNum = $mapaMeses[strtolower(substr($mesInput,0,3))] ?? null;
    }
    if ($diaNum && $mesNum && checkdate((int)$mesNum, (int)$diaNum, (int)$ano)) {
        return "$ano-$mesNum-$diaNum";
    }
    return null;
}

function formatarHoraParaBanco($horaStr) {
    if (empty($horaStr)) return null;
    if (preg_match('/^([01]?[0-9]|2[0-3]):([0-5][0-9])$/', $horaStr)) {
        return $horaStr . ':00';
    }
    if (preg_match('/^([01]?[0-9]|2[0-3]):([0-5][0-9]):([0-5][0-9])$/', $horaStr)) {
        return $horaStr;
    }
    return null;
}

function fecharConexaoTurnosESair($conexaoMysqli, $jsonData) {
    if (isset($conexaoMysqli) && $conexaoMysqli instanceof \mysqli) { 
        mysqli_close($conexaoMysqli);
    }
    echo json_encode($jsonData);
    exit;
}

$novoCsrfTokenParaCliente = null;
$userIdLogging = $_SESSION['usuario_id'] ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
        fecharConexaoTurnosESair($conexao, ['success' => false, 'message' => 'Acesso negado. Sessão inválida.']);
    }
    $input = json_decode(file_get_contents('php://input'), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        $logger->log('ERROR', 'JSON de entrada inválido.', ['user_id' => $userIdLogging, 'json_error' => json_last_error_msg()]);
        fecharConexaoTurnosESair($conexao, ['success' => false, 'message' => 'Requisição inválida (JSON).']);
    }
    if (!isset($input['csrf_token']) || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $input['csrf_token'])) {
        $logger->log('SECURITY_WARNING', 'Falha na validação do CSRF token (turnos).', ['user_id' => $userIdLogging, 'acao' => $input['acao'] ?? 'desconhecida']);
        fecharConexaoTurnosESair($conexao, ['success' => false, 'message' => 'Erro de segurança.']);
    }
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    $novoCsrfTokenParaCliente = $_SESSION['csrf_token'];

} else if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
        fecharConexaoTurnosESair($conexao, ['success' => false, 'message' => 'Acesso negado.']);
    }
    if (empty($_SESSION['csrf_token'])) { $_SESSION['csrf_token'] = bin2hex(random_bytes(32));  }
    $novoCsrfTokenParaCliente = $_SESSION['csrf_token'];
} else {
    http_response_code(405);
    fecharConexaoTurnosESair($conexao, ['success' => false, 'message' => 'Método não suportado.']);
}
$userId = $_SESSION['usuario_id'];


if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $anoFiltro = filter_input(INPUT_GET, 'ano', FILTER_VALIDATE_INT) ?: (int)date('Y');
    $mesFiltro = filter_input(INPUT_GET, 'mes', FILTER_VALIDATE_INT) ?: (int)date('m');

    if ($mesFiltro < 1 || $mesFiltro > 12) {
        fecharConexaoTurnosESair($conexao, ['success' => false, 'message' => 'Parâmetros de ano/mês inválidos.', 'csrf_token' => $novoCsrfTokenParaCliente]);
    }
    $sql = "SELECT `id`,
                   DATE_FORMAT(`data`, '%d/%m') AS `data_formatada`,
                   `data` AS `data_original_db`,
                   TIME_FORMAT(`hora_inicio`, '%H:%i') AS `hora_inicio_formatada`,
                   `hora_inicio` AS `hora_inicio_original_db`,
                   TIME_FORMAT(`hora_fim`, '%H:%i') AS `hora_fim_formatada`,
                   `hora_fim` AS `hora_fim_original_db`,
                   `colaborador`
            FROM `turnos`
            WHERE `criado_por_usuario_id` = ? AND YEAR(`data`) = ? AND MONTH(`data`) = ?
            ORDER BY `data` ASC, `hora_inicio` ASC";

    $stmt = mysqli_prepare($conexao, $sql);
    if (!$stmt) {
        $logger->log('ERROR', 'Erro ao preparar consulta GET turnos (MySQLi).', ['mysql_error' => mysqli_error($conexao), 'user_id' => $userId]);
        fecharConexaoTurnosESair($conexao, ['success' => false, 'message' => 'Erro ao preparar consulta.', 'csrf_token' => $novoCsrfTokenParaCliente]);
    }
    mysqli_stmt_bind_param($stmt, "iii", $userId, $anoFiltro, $mesFiltro);
    if (!mysqli_stmt_execute($stmt)) {
        $logger->log('ERROR', 'Erro ao executar consulta GET turnos (MySQLi).', ['mysql_stmt_error' => mysqli_stmt_error($stmt), 'user_id' => $userId]);
        mysqli_stmt_close($stmt);
        fecharConexaoTurnosESair($conexao, ['success' => false, 'message' => 'Erro ao executar consulta.', 'csrf_token' => $novoCsrfTokenParaCliente]);
    }
    $result = mysqli_stmt_get_result($stmt);
    $turnos_carregados = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $row['data'] = $row['data_original_db'];
        $row['hora_inicio'] = $row['hora_inicio_formatada'];
        $row['hora_fim'] = $row['hora_fim_formatada'];
        unset($row['data_original_db'], $row['hora_inicio_original_db'], $row['hora_fim_original_db']);
        $turnos_carregados[] = $row;
    }
    mysqli_free_result($result);
    mysqli_stmt_close($stmt);
    fecharConexaoTurnosESair($conexao, ['success' => true, 'message' => 'Turnos carregados.', 'data' => $turnos_carregados, 'csrf_token' => $novoCsrfTokenParaCliente]);
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $input['acao'] ?? null;

    if ($acao === 'excluir_turnos') {
        $idsParaExcluir = $input['ids_turnos'] ?? [];
        if (empty($idsParaExcluir)) {
            fecharConexaoTurnosESair($conexao, ['success' => false, 'message' => 'Nenhum ID fornecido para exclusão.', 'csrf_token' => $novoCsrfTokenParaCliente]);
        }
        $idsValidos = array_filter(array_map('intval', $idsParaExcluir), fn($id) => $id > 0);
        if (empty($idsValidos)) {
            fecharConexaoTurnosESair($conexao, ['success' => false, 'message' => 'IDs de turno inválidos.', 'csrf_token' => $novoCsrfTokenParaCliente]);
        }

        $placeholders = implode(',', array_fill(0, count($idsValidos), '?'));
        
        $sql_delete = "DELETE FROM `turnos` WHERE `id` IN ($placeholders) AND `criado_por_usuario_id` = ?";
        $stmt_delete = mysqli_prepare($conexao, $sql_delete);

        if ($stmt_delete) {
            $types_delete = str_repeat('i', count($idsValidos)) . 'i';
            $params_delete_bind = array_merge($idsValidos, [$userId]);
            mysqli_stmt_bind_param($stmt_delete, $types_delete, ...$params_delete_bind);

            if (mysqli_stmt_execute($stmt_delete)) {
                $numLinhasAfetadas = mysqli_stmt_affected_rows($stmt_delete);
                $logger->log('INFO', "$numLinhasAfetadas turno(s) excluído(s) do BD (MySQLi).", ['user_id' => $userId, 'ids' => $idsValidos, 'affected_rows' => $numLinhasAfetadas]);
                mysqli_stmt_close($stmt_delete); // Fechar statement APÓS uso bem-sucedido
                fecharConexaoTurnosESair($conexao, ['success' => true, 'message' => "$numLinhasAfetadas turno(s) excluído(s) com sucesso.", 'csrf_token' => $novoCsrfTokenParaCliente]);
            } else {
                $logger->log('ERROR', 'Falha ao executar exclusão de turnos BD (MySQLi).', ['user_id' => $userId, 'mysql_stmt_error' => mysqli_stmt_error($stmt_delete)]);
                mysqli_stmt_close($stmt_delete); // Fechar statement APÓS tentativa de uso falha
                fecharConexaoTurnosESair($conexao, ['success' => false, 'message' => 'Erro ao excluir turnos do banco de dados.', 'csrf_token' => $novoCsrfTokenParaCliente]);
            }
        } else { 
            $logger->log('ERROR', 'Falha ao preparar exclusão de turnos BD (MySQLi).', ['user_id' => $userId, 'mysql_error' => mysqli_error($conexao)]);
            fecharConexaoTurnosESair($conexao, ['success' => false, 'message' => 'Erro ao preparar exclusão de turnos.', 'csrf_token' => $novoCsrfTokenParaCliente]);
        }

    } elseif ($acao === 'salvar_turnos') {
        $dadosTurnosRecebidos = $input['turnos'] ?? [];
        if (empty($dadosTurnosRecebidos) || !is_array($dadosTurnosRecebidos)) {
            fecharConexaoTurnosESair($conexao, ['success' => false, 'message' => 'Nenhum dado de turno recebido.', 'data' => [], 'csrf_token' => $novoCsrfTokenParaCliente]);
        }
        $errosOperacao = [];
        $anoReferenciaTurnosSalvos = null;
        $mesReferenciaRecarga = date('m'); 
        $sql_insert = "INSERT INTO `turnos` (`data`, `hora_inicio`, `hora_fim`, `colaborador`, `criado_por_usuario_id`) VALUES (?, ?, ?, ?, ?)";
        $sql_update = "UPDATE `turnos` SET `data` = ?, `hora_inicio` = ?, `hora_fim` = ?, `colaborador` = ? WHERE `id` = ? AND `criado_por_usuario_id` = ?";
        $stmt_insert_prepared = mysqli_prepare($conexao, $sql_insert);
        $stmt_update_prepared = mysqli_prepare($conexao, $sql_update);
        if (!$stmt_insert_prepared || !$stmt_update_prepared) {
            $logger->log('ERROR', 'Falha ao preparar statements INSERT/UPDATE para turnos (MySQLi).', ['mysql_error' => mysqli_error($conexao), 'user_id' => $userId]);
            if ($stmt_insert_prepared) mysqli_stmt_close($stmt_insert_prepared);
            if ($stmt_update_prepared) mysqli_stmt_close($stmt_update_prepared);
            fecharConexaoTurnosESair($conexao, ['success' => false, 'message' => 'Erro ao preparar operações de salvamento de turnos.', 'csrf_token' => $novoCsrfTokenParaCliente]);
        }
        foreach ($dadosTurnosRecebidos as $turno) {
            $turnoIdCliente = $turno['id'] ?? null;
            $dataStr = $turno['data'] ?? null;
            $anoForm = $turno['ano'] ?? date('Y');
            $horaInicioStr = $turno['hora_inicio'] ?? null;
            $horaFimStr = $turno['hora_fim'] ?? null;
            $colaboradorNome = isset($turno['colaborador']) ? trim($turno['colaborador']) : null;
            if (!$anoReferenciaTurnosSalvos) $anoReferenciaTurnosSalvos = $anoForm;
            if(empty($mesReferenciaRecarga) && !empty($dataStr)) { 
                $dataPrimeiroTurno = formatarDataParaBanco($dataStr, $anoForm);
                if($dataPrimeiroTurno) {
                    try { $dtObjRecarga = new DateTime($dataPrimeiroTurno); $mesReferenciaRecarga = $dtObjRecarga->format('m'); } catch(Exception $e) { /* mantém o default */ }
                }
            }
            $dataFormatadaBanco = formatarDataParaBanco($dataStr, $anoForm);
            $horaInicioDb = formatarHoraParaBanco($horaInicioStr);
            $horaFimDb = formatarHoraParaBanco($horaFimStr);
            if (!$dataFormatadaBanco || !$horaInicioDb || !$horaFimDb || empty($colaboradorNome)) {
                $errosOperacao[] = "Turno para '{$colaboradorNome}' em '{$dataStr}' com dados incompletos/inválidos."; continue;
            }
            $inicioTs = strtotime($dataFormatadaBanco . ' ' . $horaInicioDb);
            $fimTs = strtotime($dataFormatadaBanco . ' ' . $horaFimDb);
             if ($fimTs <= $inicioTs) {
                $hFimExploded = explode(':', $horaFimDb);
                $hIniExploded = explode(':', $horaInicioDb);
                $hFimInt = count($hFimExploded) > 0 ? (int)$hFimExploded[0] : 25;
                $hIniInt = count($hIniExploded) > 0 ? (int)$hIniExploded[0] : -1;
                if (!($hFimInt < 6 && $hIniInt > 18)) {
                    $errosOperacao[] = "Hora Fim ({$horaFimStr}) deve ser posterior à Hora Início ({$horaInicioStr}) para {$colaboradorNome} em {$dataStr}, a menos que seja um turno noturno válido.";
                    continue;
                }
            }
            $isUpdate = ($turnoIdCliente && substr((string)$turnoIdCliente, 0, 4) !== "new-");
            if ($isUpdate) {
                $turnoIdRealDb = (int)$turnoIdCliente;
                mysqli_stmt_bind_param($stmt_update_prepared, "ssssii", $dataFormatadaBanco, $horaInicioDb, $horaFimDb, $colaboradorNome, $turnoIdRealDb, $userId);
                if (!mysqli_stmt_execute($stmt_update_prepared)) {
                    $errosOperacao[] = "Erro ao ATUALIZAR turno ID {$turnoIdRealDb}: " . mysqli_stmt_error($stmt_update_prepared);
                }
            } else {
                mysqli_stmt_bind_param($stmt_insert_prepared, "ssssi", $dataFormatadaBanco, $horaInicioDb, $horaFimDb, $colaboradorNome, $userId);
                if (!mysqli_stmt_execute($stmt_insert_prepared)) {
                    $errosOperacao[] = "Erro ao INSERIR turno para {$colaboradorNome} em {$dataStr}: " . mysqli_stmt_error($stmt_insert_prepared);
                }
            }
        }
        mysqli_stmt_close($stmt_insert_prepared);
        mysqli_stmt_close($stmt_update_prepared);
        $anoReferenciaRecarga = $anoReferenciaTurnosSalvos ?? date('Y');
        $mesReferenciaRecarga = $mesReferenciaRecarga ?? date('m');
        $sql_recarregar = "SELECT `id`,
                                  DATE_FORMAT(`data`, '%d/%m') AS `data_formatada`,
                                  `data` AS `data_original_db`,
                                  TIME_FORMAT(`hora_inicio`, '%H:%i') AS `hora_inicio_formatada`,
                                  `hora_inicio` AS `hora_inicio_original_db`,
                                  TIME_FORMAT(`hora_fim`, '%H:%i') AS `hora_fim_formatada`,
                                  `hora_fim` AS `hora_fim_original_db`,
                                  `colaborador`
                           FROM `turnos`
                           WHERE `criado_por_usuario_id` = ? AND YEAR(`data`) = ? AND MONTH(`data`) = ?
                           ORDER BY `data` ASC, `hora_inicio` ASC";
        $stmt_recarregar = mysqli_prepare($conexao, $sql_recarregar);
        $turnosRetorno = [];
        if($stmt_recarregar){
            mysqli_stmt_bind_param($stmt_recarregar, "iii", $userId, $anoReferenciaRecarga, $mesReferenciaRecarga);
            if (mysqli_stmt_execute($stmt_recarregar)) {
                $result_recarregar = mysqli_stmt_get_result($stmt_recarregar);
                while ($row = mysqli_fetch_assoc($result_recarregar)) {
                    $row['data'] = $row['data_original_db'];
                    $row['hora_inicio'] = $row['hora_inicio_formatada'];
                    $row['hora_fim'] = $row['hora_fim_formatada'];
                    unset($row['data_original_db'], $row['hora_inicio_original_db'], $row['hora_fim_original_db']);
                    $turnosRetorno[] = $row;
                }
                mysqli_free_result($result_recarregar);
            } else {
                $logger->log('ERROR', 'Falha ao executar recarregamento de turnos (MySQLi).', ['user_id' => $userId, 'mysql_stmt_error' => mysqli_stmt_error($stmt_recarregar)]);
            }
            mysqli_stmt_close($stmt_recarregar);
        } else {
            $logger->log('ERROR', 'Falha ao preparar recarregamento de turnos (MySQLi).', ['user_id' => $userId, 'mysql_error' => mysqli_error($conexao)]);
        }
        if (!empty($errosOperacao)) {
            fecharConexaoTurnosESair($conexao, ['success' => false, 'message' => 'Ocorreram erros: ' . implode("; ", $errosOperacao), 'data' => $turnosRetorno, 'csrf_token' => $novoCsrfTokenParaCliente]);
        } else {
            fecharConexaoTurnosESair($conexao, ['success' => true, 'message' => 'Turnos salvos com sucesso!', 'data' => $turnosRetorno, 'csrf_token' => $novoCsrfTokenParaCliente]);
        }
    } else {
        $logger->log('WARNING', 'Ação POST desconhecida em salvar_turnos.', ['acao' => $acao, 'user_id' => $userId]);
        fecharConexaoTurnosESair($conexao, ['success' => false, 'message' => 'Ação desconhecida.', 'csrf_token' => $novoCsrfTokenParaCliente]);
    }
}

// Fallback final
$logger->log('ERROR', 'Método não tratado ou GET sem parâmetros válidos.', ['method' => $_SERVER['REQUEST_METHOD'] ?? 'N/A']);
fecharConexaoTurnosESair($conexao, ['success' => false, 'message' => 'Requisição inválida.', 'csrf_token' => $novoCsrfTokenParaCliente ?? bin2hex(random_bytes(32)) ]);

