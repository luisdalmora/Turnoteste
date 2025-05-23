<?php
// conexao.php (Adaptado para MySQLi)

// Definição das variáveis de conexão para MySQL
// Substitua pelos seus dados de conexão MySQL
$db_servername_mysql = "ballast.proxy.rlwy.net:37421"; // Endereço do servidor MySQL (geralmente localhost)
$db_username_mysql   = "root";       // Nome de usuário do MySQL
$db_password_mysql   = "MFIuQYEcBgVYKyAxMIjwFzkiyPSRcJff";         // Senha do MySQL
$db_database_mysql   = "simposto";      // Nome do banco de dados MySQL

// Tentativa de estabelecer a conexão com o banco de dados MySQL
$conexao = mysqli_connect($db_servername_mysql, $db_username_mysql, $db_password_mysql, $db_database_mysql);

// Verificação da conexão
if ($conexao === false) {
    // Se a conexão falhar, registra o erro e encerra o script.
    // Em um ambiente de produção, evite exibir mysqli_connect_error() diretamente ao usuário.
    $error_msg = "Erro de conexão com o banco de dados MySQL em conexao.php: (" . mysqli_connect_errno() . ") " . mysqli_connect_error();
    error_log($error_msg); // Loga o erro no log do servidor

    // Resposta genérica para o usuário ou tratamento específico da API
    // Se for uma API JSON, você pode querer retornar um JSON:
    // header('Content-Type: application/json');
    // http_response_code(503); // Service Unavailable
    // echo json_encode(['success' => false, 'message' => 'Erro crítico: não foi possível conectar ao banco de dados.']);
    // exit;

    die($error_msg); // Para desenvolvimento, pode ser útil. Em produção, remova ou substitua.
}

// Define o charset da conexão para UTF-8 (ou utf8mb4 para suporte completo a Unicode)
if (!mysqli_set_charset($conexao, "utf8mb4")) {
    $error_msg = "Erro ao definir o charset da conexão MySQL para utf8mb4: " . mysqli_error($conexao);
    error_log($error_msg);
    // Considere se isso é um erro fatal para sua aplicação
}

// A variável $conexao (objeto mysqli) permanece disponível se este script for incluído por outro.
