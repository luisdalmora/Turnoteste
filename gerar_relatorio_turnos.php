<?php
// gerar_relatorio_turnos.php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/conexao.php'; // Agora $conexao é um objeto MySQLi
require_once __DIR__ . '/LogHelper.php';

// Assegura que a sessão foi iniciada (config.php já deve fazer isso)
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$logger = new LogHelper($conexao); // $conexao é MySQLi
header('Content-Type: application/json');

// --- Verificação de Sessão e CSRF Token ---
if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Acesso negado. Sessão inválida.']);
    if (isset($conexao) && $conexao) mysqli_close($conexao);
    exit;
}
$userId = $_SESSION['usuario_id'];

// CSRF Token (lógica original mantida, apenas adaptando para fechar conexão em saídas antecipadas)
if (!isset($_GET['csrf_token']) || !isset($_SESSION['csrf_token_reports']) || !hash_equals($_SESSION['csrf_token_reports'], $_GET['csrf_token'])) {
    $logger->log('SECURITY_WARNING', 'Falha CSRF token em gerar_relatorio_turnos (GET).', ['user_id' => $userId]);
    // A lógica original não saía aqui, apenas logava. Mantendo esse comportamento.
}
$_SESSION['csrf_token_reports'] = bin2hex(random_bytes(32));
$novoCsrfTokenParaCliente = $_SESSION['csrf_token_reports'];

$data_inicio_str = $_GET['data_inicio'] ?? null;
$data_fim_str = $_GET['data_fim'] ?? null;
$colaborador_filtro = $_GET['colaborador'] ?? '';

if (empty($data_inicio_str) || empty($data_fim_str)) {
    echo json_encode(['success' => false, 'message' => 'Datas de início e fim são obrigatórias.', 'csrf_token' => $novoCsrfTokenParaCliente]);
    if (isset($conexao) && $conexao) mysqli_close($conexao);
    exit;
}

try {
    $data_inicio_obj = new DateTime($data_inicio_str);
    $data_fim_obj = new DateTime($data_fim_str);
    if ($data_inicio_obj > $data_fim_obj) {
        echo json_encode(['success' => false, 'message' => 'Data de início não pode ser posterior à data de fim.', 'csrf_token' => $novoCsrfTokenParaCliente]);
        if (isset($conexao) && $conexao) mysqli_close($conexao);
        exit;
    }
} catch (Exception $e) {
    $logger->log('WARNING', 'Formato de data inválido para relatório.', ['get_data' => $_GET, 'user_id' => $userId, 'error' => $e->getMessage()]);
    echo json_encode(['success' => false, 'message' => 'Formato de data inválido (esperado YYYY-MM-DD).', 'csrf_token' => $novoCsrfTokenParaCliente]);
    if (isset($conexao) && $conexao) mysqli_close($conexao);
    exit;
}

// SQL Adaptado para MySQL
// Usando DATE_FORMAT e TIME_FORMAT
// Selecionando as colunas originais de data e hora para cálculo preciso da duração.
$sql = "SELECT 
            `t`.`data`, 
            DATE_FORMAT(`t`.`data`, '%d/%m/%Y') AS `data_formatada`, 
            `t`.`colaborador`, 
            `t`.`hora_inicio`,
            `t`.`hora_fim`,
            TIME_FORMAT(`t`.`hora_inicio`, '%H:%i') AS `hora_inicio_formatada`,
            TIME_FORMAT(`t`.`hora_fim`, '%H:%i') AS `hora_fim_formatada`
        FROM 
            `turnos` `t`
        WHERE 
            `t`.`data` BETWEEN ? AND ? 
            AND `t`.`criado_por_usuario_id` = ? ";

$params_query_values = [$data_inicio_obj->format('Y-m-d'), $data_fim_obj->format('Y-m-d'), $userId];
$param_types = "ssi"; // s: string, i: integer

if (!empty($colaborador_filtro)) {
    $sql .= " AND `t`.`colaborador` = ? ";
    $params_query_values[] = $colaborador_filtro;
    $param_types .= "s";
}
$sql .= " ORDER BY `t`.`data` ASC, `t`.`colaborador` ASC, `t`.`hora_inicio` ASC";

$stmt = mysqli_prepare($conexao, $sql);

if ($stmt === false) {
    $error_msg_db = mysqli_error($conexao);
    $logger->log('ERROR', 'Falha ao preparar statement para gerar relatório.', ['mysql_error' => $error_msg_db, 'user_id' => $userId]);
    echo json_encode(['success' => false, 'message' => 'Erro interno ao preparar consulta.', 'csrf_token' => $novoCsrfTokenParaCliente]);
    if (isset($conexao) && $conexao) mysqli_close($conexao);
    exit;
}

// mysqli_stmt_bind_param precisa que os valores sejam passados por referência
mysqli_stmt_bind_param($stmt, $param_types, ...$params_query_values);

if (!mysqli_stmt_execute($stmt)) {
    $error_msg_db = mysqli_stmt_error($stmt);
    $logger->log('ERROR', 'Falha ao executar query para gerar relatório.', ['mysql_error' => $error_msg_db, 'user_id' => $userId]);
    echo json_encode(['success' => false, 'message' => 'Erro interno ao executar consulta.', 'csrf_token' => $novoCsrfTokenParaCliente]);
    mysqli_stmt_close($stmt);
    if (isset($conexao) && $conexao) mysqli_close($conexao);
    exit;
}

$result = mysqli_stmt_get_result($stmt);
$turnos_db = [];
while ($row = mysqli_fetch_assoc($result)) {
    $turnos_db[] = $row;
}
mysqli_free_result($result);
mysqli_stmt_close($stmt);

$turnos_processados = [];
$total_geral_horas_decimal = 0;

foreach ($turnos_db as $turno_db_row) {
    $duracao_decimal = 0;
    $duracao_formatada_str = "00h00min";

    // Com MySQLi, $turno_db_row['data'], $turno_db_row['hora_inicio'], $turno_db_row['hora_fim']
    // serão strings no formato 'YYYY-MM-DD' para data e 'HH:MM:SS' para hora.
    if (!empty($turno_db_row['data']) && !empty($turno_db_row['hora_inicio']) && !empty($turno_db_row['hora_fim'])) {
        try {
            $data_original_turno = $turno_db_row['data']; // Ex: "2023-10-27"
            $hora_inicio_str = $turno_db_row['hora_inicio']; // Ex: "08:00:00"
            $hora_fim_str = $turno_db_row['hora_fim'];     // Ex: "17:00:00"

            $inicio = new DateTime($data_original_turno . ' ' . $hora_inicio_str);
            $fim = new DateTime($data_original_turno . ' ' . $hora_fim_str);

            if ($fim <= $inicio) { // Turno que passa da meia-noite
                $fim->add(new DateInterval('P1D'));
            }
            $intervalo = $inicio->diff($fim);
            
            // Cálculo da duração em decimal
            $duracao_em_minutos = ($intervalo->days * 24 * 60) + ($intervalo->h * 60) + $intervalo->i;
            $duracao_decimal = $duracao_em_minutos / 60.0;
            
            $total_geral_horas_decimal += $duracao_decimal;
            
            // Formatação da duração (total de horas e minutos)
            $total_horas_no_intervalo = ($intervalo->days * 24) + $intervalo->h;
            $duracao_formatada_str = sprintf('%02dh%02dmin', $total_horas_no_intervalo, $intervalo->i);

        } catch (Exception $e) {
            $logger->log('WARNING', 'Erro ao calcular duração de turno para relatório.', ['turno_data' => $turno_db_row, 'error' => $e->getMessage(), 'user_id' => $userId]);
            // Não sobrescrever $duracao_formatada_str para manter "00h00min" em caso de erro
        }
    }

    $turnos_processados[] = [
        'data_formatada'        => $turno_db_row['data_formatada'], // Já formatado na query SQL
        'colaborador'           => $turno_db_row['colaborador'],
        'hora_inicio_formatada' => $turno_db_row['hora_inicio_formatada'], // Já formatado na query SQL
        'hora_fim_formatada'    => $turno_db_row['hora_fim_formatada'],   // Já formatado na query SQL
        'duracao_formatada'     => $duracao_formatada_str
    ];
}

echo json_encode([
    'success'             => true,
    'turnos'              => $turnos_processados,
    'total_geral_horas'   => round($total_geral_horas_decimal, 2),
    'total_turnos'        => count($turnos_processados),
    'csrf_token'          => $novoCsrfTokenParaCliente
]);

if (isset($conexao) && $conexao) {
    mysqli_close($conexao);
}
