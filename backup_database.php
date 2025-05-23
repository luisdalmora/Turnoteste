<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/conexao.php'; // Agora conecta ao MySQL e define $conexao (mysqli), $db_servername_mysql, $db_username_mysql, etc.
require_once __DIR__ . '/LogHelper.php';

// Se o LogHelper foi instanciado em conexao.php e $conexao é mysqli,
// $logger = new LogHelper($conexao); deve funcionar se LogHelper for adaptado.
// Se LogHelper não foi adaptado para mysqli, pode causar erro.
// Para este exemplo, assumimos que LogHelper pode lidar com a conexão mysqli ou não a usa para operações de BD.
$logger = new LogHelper($conexao);
header('Content-Type: application/json');

// Validação de método e CSRF
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["success" => false, "message" => "Método não permitido."]);
    exit;
}

if (session_status() == PHP_SESSION_NONE) {
    session_start(); // Garantir que a sessão está iniciada
}

$input = json_decode(file_get_contents('php://input'), true);
if (!isset($input['csrf_token_backup']) || !isset($_SESSION['csrf_token_backup']) || !hash_equals($_SESSION['csrf_token_backup'], $input['csrf_token_backup'])) {
    $userIdForLog = $_SESSION['usuario_id'] ?? 'N/A_CSRF_FAIL';
    $logger->log('SECURITY_WARNING', 'Falha CSRF token em backup_database.php.', ['user_id' => $userIdForLog]);
    echo json_encode(['success' => false, 'message' => 'Erro de segurança (token inválido).']);
    exit;
}
// Regenerar token após uso, se desejado (não feito no original, mas boa prática em alguns cenários)
// $_SESSION['csrf_token_backup'] = bin2hex(random_bytes(32));


if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    $logger->log('SECURITY_WARNING', 'Tentativa de acesso não autenticado ao backup_database.php.');
    echo json_encode(["success" => false, "message" => "Acesso não autorizado."]);
    exit;
}
$userIdLogado = $_SESSION['usuario_id'];
// Adicionar verificação de role de administrador se necessário:
// if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
//     $logger->log('SECURITY_WARNING', 'Tentativa de backup por usuário não admin.', ['user_id' => $userIdLogado]);
//     echo json_encode(["success" => false, "message" => "Permissão negada."]);
//     exit;
// }

// As variáveis $db_servername_mysql, $db_username_mysql, $db_password_mysql, $db_database_mysql
// devem estar definidas em conexao.php
if (!isset($db_database_mysql, $db_username_mysql, $db_password_mysql, $db_servername_mysql)) {
    $logger->log('ERROR', 'Variáveis de conexão MySQL não definidas. Verifique conexao.php.', ['user_id' => $userIdLogado]);
    echo json_encode(["success" => false, "message" => "Erro interno: Configuração de conexão incompleta."]);
    exit;
}

$backupFileBase = $db_database_mysql . '_backup_' . date("Ymd_His");
$backupFile = $backupFileBase . '.sql'; // Backup MySQL geralmente é .sql
$backupFolder = __DIR__ . DIRECTORY_SEPARATOR . 'backups' . DIRECTORY_SEPARATOR;

if (!is_dir($backupFolder)) {
    if (!mkdir($backupFolder, 0775, true)) { // 0775 é uma permissão comum
        $logger->log('ERROR', 'Falha ao criar pasta de backups.', ['path' => $backupFolder, 'user_id' => $userIdLogado]);
        echo json_encode(["success" => false, "message" => "Erro interno: Não foi possível criar a pasta de backups."]);
        exit;
    }
}
if (!is_writable($backupFolder)) {
    $logger->log('ERROR', 'Pasta de backups sem permissão de escrita para o PHP.', ['path' => $backupFolder, 'user_id' => $userIdLogado]);
    echo json_encode(["success" => false, "message" => "Erro interno: A pasta de backups não tem permissão de escrita."]);
    exit;
}

$fullPathToBackup = $backupFolder . $backupFile;

// Construir o comando mysqldump
// ATENÇÃO: Passar senha na linha de comando é um risco de segurança.
// Considere usar um arquivo de opções do MySQL (my.cnf / .my.cnf) para mysqldump.
// Ex: mysqldump --defaults-extra-file=/path/to/my_config.cnf NOME_DO_BANCO > backup.sql
// Ou usar variáveis de ambiente MYSQL_USER, MYSQL_PASSWORD (menos seguro que defaults-file).
// O uso de escapeshellarg é crucial para segurança.

$mysqldumpPath = 'mysqldump'; // Tenta usar o mysqldump do PATH do sistema.
                              // Se não funcionar, especifique o caminho completo, ex: '/usr/bin/mysqldump' ou 'C:\\xampp\\mysql\\bin\\mysqldump.exe'

// Verifique se o utilitário mysqldump está disponível
// Você pode adicionar uma verificação mais robusta para a existência do `mysqldumpPath`
// ou se `shell_exec` está habilitado.

$command = sprintf(
    '%s --user=%s --password=%s --host=%s --port=%s --single-transaction --skip-lock-tables --routines --triggers %s > %s 2>&1',
    $mysqldumpPath, // Caminho para o mysqldump
    escapeshellarg($db_username_mysql),
    escapeshellarg($db_password_mysql), // RISCO DE SEGURANÇA!
    escapeshellarg($db_servername_mysql),
    escapeshellarg(isset($db_port_mysql) ? $db_port_mysql : 3306), // Adicionar porta, padrão 3306
    escapeshellarg($db_database_mysql),
    escapeshellarg($fullPathToBackup)
);
// Adicionado: --single-transaction (para InnoDB, não bloqueia tabelas), --skip-lock-tables, --routines, --triggers
// Adicionado: --port (definir $db_port_mysql em conexao.php se não for 3306)
// Adicionado: 2>&1 para redirecionar stderr para stdout para capturar erros no $output.

$logger->log('INFO', 'Tentando executar comando mysqldump.', [
    'user_id' => $userIdLogado,
    'command_preview' => sprintf( // Não logar a senha
        '%s --user=%s --password=*** --host=%s --port=%s --single-transaction --skip-lock-tables --routines --triggers %s > %s',
        $mysqldumpPath,
        $db_username_mysql,
        $db_servername_mysql,
        isset($db_port_mysql) ? $db_port_mysql : 3306,
        $db_database_mysql,
        $fullPathToBackup
    )
]);

$output = shell_exec($command);
$criticalErrorOccurred = false;
$userVisibleError = "Falha ao executar o backup do MySQL.";

// Verificar o resultado
// shell_exec retorna NULL em caso de erro ou se o comando não produzir saída.
// Se $output não for NULL e contiver mensagens de erro, ou se o arquivo não for criado/estiver vazio.

if ($output !== null && $output !== '') { // Se mysqldump produziu alguma saída (geralmente erros)
    // Verificar por strings comuns de erro no output.
    // mysqldump geralmente é silencioso em caso de sucesso.
    if (preg_match('/error|denied|fail|unable/i', $output) && !preg_match('/\[warning\]|Using a password on the command line interface can be insecure/i', $output) ) {
        $criticalErrorOccurred = true;
        $logger->log('ERROR', 'mysqldump produziu saída que parece ser um erro.', ['user_id' => $userIdLogado, 'output' => $output, 'file_path' => $fullPathToBackup]);
        $userVisibleError .= " Detalhe da saída: " . htmlentities(substr($output, 0, 250));
    } else {
        // Pode haver warnings que não são críticos, mas vale a pena logar
        $logger->log('INFO', 'mysqldump produziu saída (possivelmente warnings).', ['user_id' => $userIdLogado, 'output' => $output, 'file_path' => $fullPathToBackup]);
    }
}


if (!$criticalErrorOccurred) {
    // Mesmo que mysqldump não retorne erro explícito, verificar se o arquivo foi criado e não está vazio.
    if (file_exists($fullPathToBackup) && filesize($fullPathToBackup) > 0) {
        $logger->log('INFO', 'Backup MySQL realizado com sucesso e arquivo verificado.', ['user_id' => $userIdLogado, 'file' => $fullPathToBackup, 'size' => filesize($fullPathToBackup)]);

        $downloadScriptName = 'download_backup_file.php';
        $siteUrl = defined('SITE_URL') ? rtrim(SITE_URL, '/') : '';
        $downloadUrl = $siteUrl . '/' . $downloadScriptName . '?file=' . urlencode(basename($fullPathToBackup));

        echo json_encode([
            "success" => true,
            "message" => "Backup do banco de dados MySQL concluído com sucesso!",
            "download_url" => $downloadUrl,
            "filename" => basename($fullPathToBackup)
        ]);
    } else {
        $criticalErrorOccurred = true; // Marcar como erro se o arquivo não for válido
        $logMessage = 'Arquivo de backup MySQL NÃO encontrado ou está vazio após comando mysqldump.';
        $logContext = [
            'user_id' => $userIdLogado,
            'file_expected' => $fullPathToBackup,
            'exists' => file_exists($fullPathToBackup),
            'size' => file_exists($fullPathToBackup) ? filesize($fullPathToBackup) : 'N/A',
            'mysqldump_output' => $output
        ];
        $logger->log('ERROR', $logMessage, $logContext);
        $userVisibleError = "Erro: O arquivo de backup MySQL não foi gerado corretamente ou está vazio.";
        if (!empty($output) && !preg_match('/Using a password on the command line interface can be insecure/i', $output)) {
             $userVisibleError .= " Detalhe da saída: " . htmlentities(substr($output,0,200));
        }
    }
}

if ($criticalErrorOccurred) {
    echo json_encode(["success" => false, "message" => $userVisibleError]);
}

if ($conexao) {
    mysqli_close($conexao);
}
exit;
