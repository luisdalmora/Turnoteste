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

$escala_sabados_db = [];

if ($conexao) {
    // DAYOFWEEK(): 1 = Domingo, 2 = Segunda, ..., 7 = Sábado
    // WEEKDAY(): 0 = Segunda, 1 = Terça, ..., 5 = Sábado, 6 = Domingo
    // Usaremos DAYOFWEEK() = 7 para Sábado.
    $sql = "SELECT DATE_FORMAT(data, '%d/%m') as data, colaborador
            FROM turnos
            WHERE YEAR(data) = ? 
              AND MONTH(data) = ? 
              AND DAYOFWEEK(data) = 7 -- Filtra por Sábados
              AND criado_por_usuario_id = ? 
            ORDER BY data ASC, hora_inicio ASC";

    $stmt = mysqli_prepare($conexao, $sql);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "iii", $ano, $mes, $userId);

        if (mysqli_stmt_execute($stmt)) {
            $result = mysqli_stmt_get_result($stmt);
            while ($row = mysqli_fetch_assoc($result)) {
                $escala_sabados_db[] = $row;
            }
            mysqli_free_result($result);
            $logger->log('INFO', 'Busca de escala sábados (da tabela turnos) realizada.', ['user_id' => $userId, 'ano' => $ano, 'mes' => $mes, 'count' => count($escala_sabados_db)]);
        } else {
            $logger->log('ERROR', 'Erro ao executar busca escala_sabados: ' . mysqli_stmt_error($stmt), ['user_id' => $userId]);
        }
        mysqli_stmt_close($stmt);
    } else {
        $logger->log('ERROR', 'Erro ao preparar query escala_sabados: ' . mysqli_error($conexao), ['user_id' => $userId]);
    }
} else {
    $logger->log('ERROR', 'Sem conexão com o banco de dados em carregar_escala_sabados.php', ['user_id' => $userId]);
}

echo json_encode(['success' => true, 'escala' => $escala_sabados_db]);

if (isset($conexao) && $conexao) {
    mysqli_close($conexao);
}
