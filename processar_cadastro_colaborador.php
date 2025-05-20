<?php
// processar_cadastro_colaborador.php

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/conexao.php'; // Agora $conexao é um objeto MySQLi
require_once __DIR__ . '/LogHelper.php';

// Assegura que a sessão foi iniciada (config.php já deve fazer isso)
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$logger = new LogHelper($conexao); // $conexao agora é MySQLi
$adminUserId = $_SESSION['usuario_id'] ?? null;

// Função auxiliar para definir flash message, fechar conexão e redirecionar
function setFlashAndRedirectColab($conexaoMysqli, $type, $message, $location) {
    $_SESSION['flash_message'] = ['type' => $type, 'message' => $message];
    if (isset($conexaoMysqli) && $conexaoMysqli) {
        mysqli_close($conexaoMysqli);
    }
    header("Location: " . $location);
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token_cad_colab']) || !hash_equals($_SESSION['csrf_token_cad_colab'], $_POST['csrf_token'])) {
        $logger->log('SECURITY_WARNING', 'Falha na validação do CSRF token ao cadastrar colaborador.', ['admin_user_id' => $adminUserId, 'posted_token' => $_POST['csrf_token'] ?? 'N/A']);
        setFlashAndRedirectColab($conexao, 'error', 'Erro de segurança (token inválido). Por favor, tente novamente.', 'cadastrar_colaborador.php');
    }
    // Opcional: Regenerar o token CSRF após um uso bem-sucedido ou falha para evitar replay,
    // mas para cadastro pode ser mais simples manter até o sucesso.
    // Se o cadastro falhar e o usuário for redirecionado, o token antigo ainda será válido para uma nova tentativa.
    // Se for um sucesso, o token na página de gerenciamento será outro.

    $nome_completo = isset($_POST['nome_completo']) ? trim($_POST['nome_completo']) : '';
    $email_input = isset($_POST['email']) ? trim($_POST['email']) : null;
    $cargo_input = isset($_POST['cargo']) ? trim($_POST['cargo']) : null;
    $ativo = isset($_POST['ativo']) ? (int)$_POST['ativo'] : 1; // Default para ativo

    if (empty($nome_completo)) {
        setFlashAndRedirectColab($conexao, 'warning', 'Nome completo é obrigatório.', 'cadastrar_colaborador.php');
    }

    $email = null;
    if (!empty($email_input)) {
        if (!filter_var($email_input, FILTER_VALIDATE_EMAIL)) {
            setFlashAndRedirectColab($conexao, 'warning', 'Formato de e-mail inválido.', 'cadastrar_colaborador.php');
        }
        $email = $email_input; // E-mail validado
    }

    // Define $cargo como null se for uma string vazia após trim, senão usa o valor
    $cargo = ($cargo_input !== null && trim($cargo_input) === '') ? null : $cargo_input;


    // SQL para MySQL: Sem OUTPUT INSERTED.id. Crases para identificadores.
    $sql = "INSERT INTO `colaboradores` (`nome_completo`, `email`, `cargo`, `ativo`) VALUES (?, ?, ?, ?)";

    $stmt = mysqli_prepare($conexao, $sql);

    if ($stmt) {
        // Tipos de parâmetros: s = string, i = integer
        // nome_completo (s), email (s), cargo (s), ativo (i) -> "sssi"
        // mysqli_stmt_bind_param lida com $email e $cargo sendo null.
        mysqli_stmt_bind_param($stmt, "sssi", $nome_completo, $email, $cargo, $ativo);

        if (mysqli_stmt_execute($stmt)) {
            $novo_colaborador_id = mysqli_insert_id($conexao); // Pega o ID do último registro inserido

            if ($novo_colaborador_id > 0) {
                $logger->log('INFO', 'Novo colaborador cadastrado com sucesso (MySQLi).', [
                    'colaborador_id' => $novo_colaborador_id,
                    'nome' => $nome_completo,
                    'admin_user_id' => $adminUserId
                ]);
                mysqli_stmt_close($stmt);
                setFlashAndRedirectColab($conexao, 'success', "Colaborador '".htmlspecialchars($nome_completo)."' cadastrado com sucesso!", 'gerenciar_colaboradores.php');
            } else {
                // Isso seria incomum se mysqli_stmt_execute retornou true
                $logger->log('ERROR', 'Cadastro de colaborador executado (MySQLi), mas ID não foi retornado.', [
                    'nome' => $nome_completo, 'admin_user_id' => $adminUserId, 'mysql_insert_id' => $novo_colaborador_id
                ]);
                mysqli_stmt_close($stmt);
                setFlashAndRedirectColab($conexao, 'error', 'Erro ao finalizar o cadastro do colaborador. ID não obtido.', 'cadastrar_colaborador.php');
            }
        } else {
            // Erro na execução do statement
            $mysql_stmt_error = mysqli_stmt_error($stmt);
            $mysql_stmt_errno = mysqli_stmt_errno($stmt);
            $logger->log('ERROR', 'Erro ao executar query de cadastro de colaborador (MySQLi).', [
                'mysql_stmt_error' => $mysql_stmt_error, 'mysql_stmt_errno' => $mysql_stmt_errno, 'nome' => $nome_completo, 'admin_user_id' => $adminUserId
            ]);

            $user_message = "Erro ao cadastrar o colaborador.";
            if ($mysql_stmt_errno == 1062) { // Código de erro do MySQL para violação de chave única (Duplicate entry)
                // Verificar se o erro é realmente sobre o e-mail (se houver outras chaves únicas)
                if (is_string($mysql_stmt_error) && stripos($mysql_stmt_error, 'email') !== false) {
                     $user_message = "Erro: O e-mail informado ('".htmlspecialchars($email)."') já está cadastrado.";
                } else {
                     $user_message = "Erro: Já existe um registro com um dos valores únicos informados (ex: e-mail).";
                }
            } else {
                $user_message .= " Detalhe técnico: " . htmlentities($mysql_stmt_error);
            }
            mysqli_stmt_close($stmt);
            setFlashAndRedirectColab($conexao, 'error', $user_message, 'cadastrar_colaborador.php');
        }
    } else {
        // Erro na preparação do statement
        $mysql_error = mysqli_error($conexao);
        $logger->log('ERROR', 'Erro ao preparar query de cadastro de colaborador (MySQLi).', ['mysql_error' => $mysql_error, 'admin_user_id' => $adminUserId]);
        setFlashAndRedirectColab($conexao, 'error', 'Erro no sistema ao tentar preparar o cadastro: ' . htmlentities($mysql_error) . '. Por favor, tente novamente.', 'cadastrar_colaborador.php');
    }

} else {
    $logger->log('WARNING', 'Acesso inválido (não POST) à página de processar_cadastro_colaborador.', ['admin_user_id' => $adminUserId]);
    // Não há $conexao para fechar aqui se nunca foi usada no fluxo GET, mas a função auxiliar verifica.
    setFlashAndRedirectColab(isset($conexao) ? $conexao : null, 'error', 'Acesso inválido. Utilize o formulário de cadastro.', 'cadastrar_colaborador.php');
}
