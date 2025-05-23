<?php
// login.php

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/conexao.php'; // Agora $conexao é um objeto MySQLi
require_once __DIR__ . '/LogHelper.php';

// Assegura que a sessão foi iniciada (config.php já deve fazer isso)
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$logger = new LogHelper($conexao); // $conexao agora é MySQLi

// Função auxiliar para fechar conexão e redirecionar
function fecharConexaoELoginRedirect($conexaoMysqli, $url) {
    if (isset($conexaoMysqli) && $conexaoMysqli) {
        mysqli_close($conexaoMysqli);
    }
    header('Location: ' . $url);
    exit;
}

if (isset($_SESSION['logado']) && $_SESSION['logado'] === true) {
    fecharConexaoELoginRedirect($conexao, 'home.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $erro_login = "";

    // Verifica se a conexão foi estabelecida com sucesso em conexao.php
    // conexao.php (versão MySQLi) já deve ter tratado falhas críticas de conexão.
    if (!$conexao || !($conexao instanceof \mysqli)) {
        $logger->log('CRITICAL', 'Conexão com BD indisponível ou inválida em login.php (MySQLi).', ['connection_status' => ($conexao ? 'Tipo inválido' : 'Não conectado')]);
        // Não usar mysqli_error() aqui se $conexao não for um objeto mysqli válido.
        fecharConexaoELoginRedirect(null, 'index.html?erro=' . urlencode("Falha crítica na conexão. Contate o suporte."));
    }

    $usuario_digitado = isset($_POST['usuario']) ? trim($_POST['usuario']) : null;
    $senha_digitada = isset($_POST['senha']) ? $_POST['senha'] : null;

    if (empty($usuario_digitado) || empty($senha_digitada)) {
        $erro_login = "Usuário e Senha são obrigatórios.";
    }

    if (empty($erro_login)) {
        // SQL adaptado para MySQL: LIMIT 1 e crases
        $sql = "SELECT `id`, `usuario`, `senha`, `nome_completo`, `email` FROM `usuarios` WHERE (`usuario` = ? OR `email` = ?) AND `ativo` = 1 LIMIT 1";
        
        $stmt = mysqli_prepare($conexao, $sql);

        if ($stmt) {
            // "ss" indica que ambos os parâmetros são strings
            mysqli_stmt_bind_param($stmt, "ss", $usuario_digitado, $usuario_digitado);

            if (mysqli_stmt_execute($stmt)) {
                $result = mysqli_stmt_get_result($stmt);
                $row = mysqli_fetch_assoc($result);
                mysqli_free_result($result);

                if ($row) { // Usuário encontrado
                    $db_id = $row['id'];
                    $db_usuario = $row['usuario'];
                    $db_senha_hash = $row['senha'];
                    $db_nome_completo = $row['nome_completo'];
                    $db_email = $row['email'];

                    if (password_verify($senha_digitada, $db_senha_hash)) {
                        session_regenerate_id(true);
                        $_SESSION['usuario_id'] = $db_id;
                        $_SESSION['usuario_nome'] = $db_usuario;
                        $_SESSION['usuario_nome_completo'] = $db_nome_completo;
                        $_SESSION['usuario_email'] = $db_email;
                        $_SESSION['logado'] = true;
                        // É uma boa prática gerar tokens CSRF específicos por formulário/ação ou um token geral após o login.
                        // O original tinha 'csrf_token', assumindo um token geral de sessão.
                        $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); 

                        $logger->log('AUTH_SUCCESS', 'Login bem-sucedido (MySQLi).', ['usuario_id' => $db_id, 'usuario' => $db_usuario]);

                        mysqli_stmt_close($stmt);
                        fecharConexaoELoginRedirect($conexao, 'home.php');
                    } else {
                        $erro_login = "Usuário ou senha incorretos.";
                        $logger->log('AUTH_FAILURE', $erro_login, ['usuario_digitado' => $usuario_digitado, 'motivo' => 'Senha não confere (MySQLi)']);
                    }
                } else { // Nenhum usuário encontrado
                    $erro_login = "Usuário ou senha incorretos, ou usuário inativo.";
                    $logger->log('AUTH_FAILURE', $erro_login, ['usuario_digitado' => $usuario_digitado, 'motivo' => 'Usuário não encontrado ou inativo (MySQLi)']);
                }
            } else {
                $mysql_stmt_error = mysqli_stmt_error($stmt);
                $erro_login = "Erro no sistema ao processar o login. Tente novamente.";
                $logger->log('ERROR', 'Falha ao executar statement de login (MySQLi).', ['mysql_stmt_error' => $mysql_stmt_error]);
            }
            mysqli_stmt_close($stmt);
        } else {
            $mysql_error = mysqli_error($conexao);
            $erro_login = "Erro no sistema ao processar o login. Tente novamente mais tarde.";
            $logger->log('ERROR', 'Falha ao preparar statement de login (MySQLi).', ['mysql_error' => $mysql_error]);
        }
    }

    if (!empty($erro_login)) {
        fecharConexaoELoginRedirect($conexao, 'index.html?erro=' . urlencode($erro_login));
    }

} else {
    // Se não for POST, redireciona para index.html (ou a página de login principal)
    fecharConexaoELoginRedirect($conexao, 'index.html');
}
