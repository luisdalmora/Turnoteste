<?php
// carregar_feriados.php
require_once __DIR__ . '/config.php';
// conexao.php não é mais estritamente necessário aqui se LogHelper e GCalHelper não usarem BD.
// Se LogHelper ainda loga no BD, conexao.php é necessário para LogHelper.
require_once __DIR__ . '/conexao.php'; // Mantido caso LogHelper use BD
require_once __DIR__ . '/LogHelper.php';
require_once __DIR__ . '/GoogleCalendarHelper.php'; // Agora simplificado

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$logger = new LogHelper($conexao); // LogHelper pode ainda precisar da conexão
$gcalHelper = new GoogleCalendarHelper($logger); // Não passa mais $conexao

header('Content-Type: application/json');

// A verificação de login pode ser mantida por segurança geral do endpoint,
// mesmo que a busca de feriados seja de um calendário público.
if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Acesso negado.']);
    if (isset($conexao) && $conexao) { mysqli_close($conexao); }
    exit;
}
// $userId = $_SESSION['usuario_id']; // Não é mais passado para listEventsFromCalendar

$ano = filter_input(INPUT_GET, 'ano', FILTER_VALIDATE_INT) ?: (int)date('Y');
$mes = filter_input(INPUT_GET, 'mes', FILTER_VALIDATE_INT) ?: (int)date('m');

$calendarId = 'pt-br.brazilian#holiday@group.v.calendar.google.com';

try {
    $timeMin = new DateTimeImmutable("{$ano}-{$mes}-01T00:00:00", new DateTimeZone('America/Sao_Paulo'));
    $timeMax = $timeMin->modify('last day of this month')->setTime(23, 59, 59);
} catch (Exception $e) {
    $logger->log('ERROR', 'Data inválida fornecida para carregar feriados.', ['ano' => $ano, 'mes' => $mes, 'error' => $e->getMessage()]);
    echo json_encode(['success' => false, 'message' => 'Data fornecida inválida.']);
    if (isset($conexao) && $conexao) { mysqli_close($conexao); }
    exit;
}

$params = [
    'orderBy' => 'startTime',
    'singleEvents' => true,
    'timeMin' => $timeMin->format(DateTimeInterface::RFC3339),
    'timeMax' => $timeMax->format(DateTimeInterface::RFC3339)
];

// $userId não é mais necessário para esta chamada
$eventos = $gcalHelper->listEventsFromCalendar($calendarId, $params);

if ($eventos === null) {
    echo json_encode(['success' => false, 'message' => 'Não foi possível buscar os feriados do Google Calendar. Verifique as configurações da API Key.']);
    if (isset($conexao) && $conexao) { mysqli_close($conexao); }
    exit;
}

$feriadosFormatados = [];
if (!empty($eventos)) {
    foreach ($eventos as $evento) {
        $dataFeriado = '';
        if (!empty($evento->start->date)) {
            try {
                $dataFeriado = (new DateTime($evento->start->date))->format('d/m/Y');
            } catch (Exception $e) {
                $logger->log('WARNING', 'Data de feriado inválida (all-day).', ['event_start_date' => $evento->start->date, 'error' => $e->getMessage()]);
                $dataFeriado = 'Data inválida';
            }
        } elseif (!empty($evento->start->dateTime)) {
             try {
                $dataFeriado = (new DateTime($evento->start->dateTime))->format('d/m/Y');
            } catch (Exception $e) {
                $logger->log('WARNING', 'Data de feriado inválida (specific time).', ['event_start_dateTime' => $evento->start->dateTime, 'error' => $e->getMessage()]);
                $dataFeriado = 'Data inválida';
            }
        }

        $feriadosFormatados[] = [
            'data' => $dataFeriado,
            'observacao' => $evento->getSummary()
        ];
    }
}

echo json_encode(['success' => true, 'feriados' => $feriadosFormatados]);

if (isset($conexao) && $conexao) {
    mysqli_close($conexao);
}
