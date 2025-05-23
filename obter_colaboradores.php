<?php
// obter_colaboradores.php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/conexao.php'; // Agora $conexao é um objeto MySQLi
// require_once __DIR__ . '/LogHelper.php'; // Descomente se for usar logs

header('Content-Type: application/json');

// Assegura que a sessão foi iniciada se a verificação de sessão for descomentada.
// config.php já deve cuidar disso.
if (session_status() == PHP_SESSION_NONE && (false)) { // O (false) é para não iniciar se não for usar
    session_start();
}

// $logger = new LogHelper($conexao); // Descomente se for usar logs. $conexao já é MySQLi.

// Função auxiliar para fechar conexão e sair
function fecharConexaoObterESair($conexaoMysqli, $jsonData) {
    if (isset($conexaoMysqli) && $conexaoMysqli) {
        mysqli_close($conexaoMysqli);
    }
    echo json_encode($jsonData);
    exit;
}

// Descomente a verificação de sessão se esta informação for sensível
// if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
//     fecharConexaoObterESair($conexao, ['success' => false, 'message' => 'Acesso não autorizado.']);
// }
// $userId = isset($_SESSION['usuario_id']) ? $_SESSION['usuario_id'] : null; // Para logging, se usado

$colaboradores = [];
// Busca apenas colaboradores ativos para os dropdowns
// Adicionando crases para nomes de tabelas e colunas (boa prática MySQL)
$sql = "SELECT `id`, `nome_completo` FROM `colaboradores` WHERE `ativo` = 1 ORDER BY `nome_completo` ASC";

// Para SELECTs simples sem parâmetros, mysqli_query() é direto.
$result = mysqli_query($conexao, $sql);

if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $colaboradores[] = $row;
    }
    mysqli_free_result($result); // Libera a memória do resultado
    fecharConexaoObterESair($conexao, ['success' => true, 'colaboradores' => $colaboradores]);
} else {
    $mysql_error = mysqli_error($conexao);
    // Se usar logger: $logger->log('ERROR', 'Falha ao buscar colaboradores ativos (MySQLi).', ['mysql_error' => $mysql_error, 'user_id' => $userId ?? null]);
    error_log("Erro ao buscar colaboradores em obter_colaboradores.php (MySQLi): " . $mysql_error); // Log de fallback
    fecharConexaoObterESair($conexao, ['success' => false, 'message' => 'Erro ao buscar lista de colaboradores.']);
}

// Fallback para fechar conexão, embora a função auxiliar já deva ter feito isso.
if (isset($conexao) && $conexao) {
    mysqli_close($conexao);
}
