<?php
// cadastrar.php

require_once __DIR__ . '/config.php'; // Inclui configurações e inicia sessão
require_once __DIR__ . '/conexao.php'; // $conexao agora é um objeto MySQLi
require_once __DIR__ . '/LogHelper.php';
require_once __DIR__ . '/EmailHelper.php';

// Assegura que a sessão foi iniciada (config.php já deve fazer isso)
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$logger = new LogHelper($conexao); // $conexao é MySQLi

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nome_completo = isset($_POST['nome_completo']) ? trim($_POST['nome_completo']) : '';
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $usuario = isset($_POST['usuario']) ? trim($_POST['usuario']) : '';
    $senha_digitada = isset($_POST['senha']) ? $_POST['senha'] : '';

    if (empty($nome_completo) || empty($email) || empty($usuario) || empty($senha_digitada)) {
        $logger->log('WARNING', 'Tentativa de cadastro com campos obrigatórios vazios.', ['post_data' => $_POST]);
        echo "Erro: Todos os campos são obrigatórios.";
        if ($conexao) mysqli_close($conexao);
        exit;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $logger->log('WARNING', 'Tentativa de cadastro com e-mail inválido.', ['email' => $email]);
        echo "Erro: Formato de e-mail inválido.";
        if ($conexao) mysqli_close($conexao);
        exit;
    }

    $senha_hash = password_hash($senha_digitada, PASSWORD_DEFAULT);
    // Para MySQL, não há `OUTPUT INSERTED.id`. Pegamos o ID com `mysqli_insert_id()` após a execução.
    // Adicionando crases (backticks) para nomes de tabelas e colunas (boa prática MySQL).
    $sql = "INSERT INTO `usuarios` (`nome_completo`, `email`, `usuario`, `senha`, `ativo`) VALUES (?, ?, ?, ?, 1)";

    $stmt = mysqli_prepare($conexao, $sql);

    if ($stmt) {
        // "ssss" indica que todos os quatro parâmetros são strings.
        mysqli_stmt_bind_param($stmt, "ssss", $nome_completo, $email, $usuario, $senha_hash);

        if (mysqli_stmt_execute($stmt)) {
            $novo_usuario_id = mysqli_insert_id($conexao); // Pega o ID do último registro inserido na conexão atual

            if ($novo_usuario_id > 0) { // Verifica se um ID válido foi retornado
                $logger->log('INFO', 'Novo usuário cadastrado com sucesso.', ['usuario_id' => $novo_usuario_id, 'usuario' => $usuario, 'email' => $email]);

                if (EmailHelper::sendRegistrationConfirmationEmail($email, $nome_completo)) {
                    $logger->log('INFO', 'E-mail de confirmação de cadastro enviado.', ['usuario_id' => $novo_usuario_id, 'email' => $email]);
                    // Definir uma mensagem flash para exibir na página de login após o redirecionamento
                    $_SESSION['flash_message'] = ['type' => 'success', 'message' => 'Cadastro realizado com sucesso! Um e-mail de confirmação foi enviado.'];
                } else {
                    $logger->log('ERROR', 'Falha ao enviar e-mail de confirmação de cadastro.', ['usuario_id' => $novo_usuario_id, 'email' => $email]);
                     $_SESSION['flash_message'] = ['type' => 'warning', 'message' => 'Cadastro realizado, mas falha ao enviar e-mail de confirmação.'];
                }

                mysqli_stmt_close($stmt);
                if ($conexao) mysqli_close($conexao);
                // Redireciona para index.html (ou login.php se for o caso)
                // O status pode ser usado para exibir a mensagem flash
                header("Location: index.html?status=cadastro_sucesso");
                exit;
            } else {
                // Não conseguiu obter o ID, mas o execute pode ter retornado true sem erro aparente de BD (improvável para INSERTs bem-sucedidos)
                $logger->log('ERROR', 'Cadastro parece ter ocorrido (execute true), mas ID do novo usuário não foi retornado.', ['usuario' => $usuario, 'email' => $email, 'mysqli_insert_id' => $novo_usuario_id]);
                echo "Erro ao finalizar o cadastro. ID do usuário não obtido.";
            }
        } else {
            // Erro na execução do statement
            $error_msg_db = mysqli_stmt_error($stmt);
            $error_code_db = mysqli_stmt_errno($stmt);
            $logger->log('ERROR', 'Erro ao executar query de cadastro.', ['mysql_error' => $error_msg_db, 'mysql_errno' => $error_code_db, 'usuario' => $usuario, 'email' => $email]);

            $user_display_error = "Erro ao cadastrar o usuário.";
            if ($error_code_db == 1062) { // Código de erro do MySQL para violação de chave única (Duplicate entry)
                if (strpos(strtolower($error_msg_db), 'email') !== false) {
                    $user_display_error = "Erro ao cadastrar: O e-mail informado já existe.";
                } elseif (strpos(strtolower($error_msg_db), 'usuario') !== false) { // Supondo que 'usuario' também seja UNIQUE
                    $user_display_error = "Erro ao cadastrar: O nome de usuário já existe.";
                } else {
                    $user_display_error = "Erro ao cadastrar: O e-mail ou nome de usuário já existe.";
                }
            } else {
                $user_display_error .= " Detalhe técnico: " . htmlentities($error_msg_db);
            }
            echo $user_display_error;
        }
        if ($stmt) mysqli_stmt_close($stmt); // Garante que o statement seja fechado se chegou aqui

    } else {
        // Erro na preparação do statement
        $error_msg_db = mysqli_error($conexao);
        $logger->log('ERROR', 'Erro ao preparar query de cadastro.', ['mysql_error' => $error_msg_db]);
        echo "Erro no sistema ao tentar preparar o cadastro: " . htmlentities($error_msg_db);
    }

} else {
    $logger->log('WARNING', 'Acesso inválido (não POST) à página de cadastro.');
    echo "Acesso inválido à página de cadastro.";
}

if (isset($conexao) && $conexao) { // Garante que a conexão seja fechada se ainda aberta
    mysqli_close($conexao);
}
