<?php
// LogHelper.php

class LogHelper {
    private $conexao; // Agora será uma conexão MySQLi

    public function __construct($db_connection) {
        // $db_connection deve ser um objeto mysqli se a conexão com o BD for usada.
        $this->conexao = $db_connection;
    }

    /**
     * Registra uma mensagem de log no banco de dados.
     *
     * @param string $level Nível do log (e.g., INFO, ERROR, WARNING, AUTH_SUCCESS, AUTH_FAILURE, GCAL_SUCCESS, GCAL_ERROR)
     * @param string $message A mensagem de log.
     * @param array $context Dados contextuais adicionais (serão convertidos para JSON).
     * @param int|null $userId ID do usuário associado ao log (opcional).
     */
    public function log($level, $message, $context = [], $userId = null) {
        if (!$this->conexao || !($this->conexao instanceof \mysqli)) { // Verifica se é uma instância válida de mysqli
            // Não pode logar sem conexão com o BD ou se a conexão não for do tipo esperado
            $timestamp = date('Y-m-d H:i:s');
            error_log("{$timestamp} LogHelper: Falha ao logar - Sem conexão com BD ou tipo de conexão inválida. Nível: {$level}, Mensagem: {$message}, Contexto: " . json_encode($context));
            return;
        }

        $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
        // Contexto pode ser nulo se vazio, para evitar armazenar '[]' explicitamente se a coluna permitir NULL
        $context_json = !empty($context) ? json_encode($context) : null;

        // Adicionando crases para nomes de tabelas e colunas
        $sql = "INSERT INTO `system_logs` (`log_level`, `message`, `context`, `ip_address`, `user_id`) VALUES (?, ?, ?, ?, ?)";
        
        $stmt = mysqli_prepare($this->conexao, $sql);

        if ($stmt) {
            // Definindo os tipos de parâmetros para mysqli_stmt_bind_param:
            // s = string, i = integer
            // log_level (s), message (s), context_json (s), ip_address (s), user_id (i)
            // $userId pode ser null, mysqli_stmt_bind_param lida com isso para tipos 'i' (integer)
            mysqli_stmt_bind_param($stmt, "ssssi", $level, $message, $context_json, $ip_address, $userId);

            if (!mysqli_stmt_execute($stmt)) {
                // Se o log falhar, registra no log de erros do PHP como fallback
                $mysql_stmt_error = mysqli_stmt_error($stmt);
                $timestamp = date('Y-m-d H:i:s');
                error_log("{$timestamp} LogHelper: Falha ao executar statement de log no BD (MySQLi). Nível: {$level}, Mensagem Original: {$message}, Erro MySQLi Stmt: " . $mysql_stmt_error . ", Contexto: " . json_encode($context));
            }
            mysqli_stmt_close($stmt);
        } else {
            // Falha ao preparar o statement
            $mysql_error = mysqli_error($this->conexao);
            $timestamp = date('Y-m-d H:i:s');
            error_log("{$timestamp} LogHelper: Falha ao preparar statement de log no BD (MySQLi). Nível: {$level}, Mensagem Original: {$message}, Erro MySQLi: " . $mysql_error . ", Contexto: " . json_encode($context));
        }
    }
}
