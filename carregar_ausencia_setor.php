<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/conexao.php';
require_once __DIR__ . '/LogHelper.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
$logger = new LogHelper($conexao);
header('Content-Type: application/json');

if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Acesso negado.']);
    if (isset($conexao) && $conexao) { mysqli_close($conexao); }
    exit;
}

$ano = filter_input(INPUT_GET, 'ano', FILTER_VALIDATE_INT) ?: (int)date('Y');
$mes = filter_input(INPUT_GET, 'mes', FILTER_VALIDATE_INT) ?: (int)date('m');
$userId = $_SESSION['usuario_id'] ?? null;

$ausencias_setor_db = [];

if ($conexao) {
    $primeiroDiaMesFiltro = sprintf('%04d-%02d-01', $ano, $mes);
    $ultimoDiaMesFiltro = date('Y-m-t', strtotime($primeiroDiaMesFiltro));

    // Query para buscar todas as ausências do usuário no período,
    // usando a coluna 'colaborador_nome' da tabela 'ausencias'.
    // O FILTRO POR 'observacoes LIKE "%Setor%"' FOI REMOVIDO.
    $sql = "SELECT DATE_FORMAT(a.data_inicio, '%d/%m') as data, 
                   a.colaborador_nome as colaborador 
            FROM ausencias a
            WHERE a.criado_por_usuario_id = ?       -- Param 1 (i)
              -- AND a.observacoes LIKE ?           -- FILTRO REMOVIDO
              AND ( 
                 (a.data_inicio >= ? AND a.data_inicio <= ?) OR  -- Params 2 (s), 3 (s)
                 (a.data_fim >= ? AND a.data_fim <= ?) OR        -- Params 4 (s), 5 (s)
                 (a.data_inicio < ? AND a.data_fim > ?)          -- Params 6 (s), 7 (s)
              )
            ORDER BY a.data_inicio ASC";
    
    $stmt = mysqli_prepare($conexao, $sql);
    if ($stmt) {
        // String de tipos agora tem um 's' a menos: i, s, s, s, s, s, s -> issssss
        mysqli_stmt_bind_param($stmt, "issssss", 
            $userId,                       // para criado_por_usuario_id = ?
            // $filtro_palavra_chave_setor,   // Removido
            $primeiroDiaMesFiltro,         // para data_inicio >= ?
            $ultimoDiaMesFiltro,           // para data_inicio <= ?
            $primeiroDiaMesFiltro,         // para data_fim >= ?
            $ultimoDiaMesFiltro,           // para data_fim <= ?
            $primeiroDiaMesFiltro,         // para data_inicio < ?
            $ultimoDiaMesFiltro            // para data_fim > ?
        );

        if (mysqli_stmt_execute($stmt)) {
            $result = mysqli_stmt_get_result($stmt);
            while ($row = mysqli_fetch_assoc($result)) {
                $ausencias_setor_db[] = [
                    'data' => $row['data'],
                    'colaborador' => $row['colaborador'] ?: 'N/A' 
                ];
            }
            mysqli_free_result($result);
            $logger->log('INFO', 'Busca de ausência setor (SEM filtro obs, usando colaborador_nome) realizada.', ['user_id' => $userId, 'ano' => $ano, 'mes' => $mes, 'count' => count($ausencias_setor_db)]);
        } else {
            $logger->log('ERROR', 'Erro ao executar busca ausencias_setor: ' . mysqli_stmt_error($stmt), ['user_id' => $userId]);
        }
        mysqli_stmt_close($stmt);
    } else {
        $logger->log('ERROR', 'Erro ao preparar query ausencias_setor: ' . mysqli_error($conexao), ['user_id' => $userId, 'sql_query' => $sql]);
    }
} else {
     $logger->log('ERROR', 'Sem conexão com o banco de dados em carregar_ausencia_setor.php', ['user_id' => $userId]);
}

echo json_encode(['success' => true, 'ausencias' => $ausencias_setor_db]);

if (isset($conexao) && $conexao) {
    mysqli_close($conexao);
}
