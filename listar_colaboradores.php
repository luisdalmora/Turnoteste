<?php
// listar_colaboradores.php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/conexao.php'; // Agora $conexao é um objeto MySQLi
require_once __DIR__ . '/LogHelper.php';

// Assegura que a sessão foi iniciada (config.php já deve fazer isso)
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// $conexao agora é um objeto MySQLi.
// Se LogHelper foi adaptado para MySQLi, isso funcionará.
$logger = new LogHelper($conexao);
header('Content-Type: application/json');

// Função auxiliar para fechar conexão e sair
function fecharConexaoListarESair($conexaoMysqli, $jsonData) {
    if (isset($conexaoMysqli) && $conexaoMysqli) {
        mysqli_close($conexaoMysqli);
    }
    echo json_encode($jsonData);
    exit;
}

if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    fecharConexaoListarESair($conexao, ['success' => false, 'message' => 'Acesso não autorizado. Faça login.']);
}
$userId = $_SESSION['usuario_id']; // Usado para logging, se necessário

$colaboradores = [];
// Adicionando crases para nomes de tabelas e colunas (boa prática MySQL)
$sql = "SELECT `id`, `nome_completo`, `email`, `cargo`, `ativo` FROM `colaboradores` ORDER BY `nome_completo` ASC";

// Para SELECTs simples sem parâmetros, mysqli_query() é direto.
$result = mysqli_query($conexao, $sql);

if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        // Se 'ativo' no MySQL for TINYINT(1), ele retornará 0 ou 1.
        // A conversão para booleano (true/false) é mantida.
        $row['ativo'] = (bool)$row['ativo'];
        $colaboradores[] = $row;
    }
    mysqli_free_result($result); // Libera a memória do resultado
    fecharConexaoListarESair($conexao, ['success' => true, 'colaboradores' => $colaboradores]);
} else {
    $error_msg_db = mysqli_error($conexao);
    $logger->log('ERROR', 'Falha ao executar query para listar colaboradores (MySQLi).', ['mysql_error' => $error_msg_db, 'user_id' => $userId]);
    fecharConexaoListarESair($conexao, ['success' => false, 'message' => 'Erro ao buscar lista de colaboradores.']);
}

// Fallback para fechar conexão, embora a função auxiliar já deva ter feito isso.
if (isset($conexao) && $conexao) {
    mysqli_close($conexao);
}
