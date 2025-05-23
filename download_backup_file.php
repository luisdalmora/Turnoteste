<?php
require_once __DIR__ . '/config.php'; // Define constantes e inicia sessão
require_once __DIR__ . '/LogHelper.php'; // Para loggar tentativas de download

// Assegura que a sessão foi iniciada (config.php já deve fazer isso)
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Instanciação do Logger:
// Se LogHelper PODE funcionar com $conexao = null (ou seja, não faz queries no BD para logar, ou tem um fallback):
// $logger = new LogHelper(null);
// Se LogHelper PRECISA de uma conexão mysqli para logar em tabelas de log, você teria que incluir conexao.php:
// require_once __DIR__ . '/conexao.php'; // Descomente se LogHelper precisa de $conexao mysqli
// $logger = new LogHelper($conexao); // E $conexao seria o objeto mysqli de conexao.php
// Para este exemplo, assumiremos que o logger pode não ser essencial para a funcionalidade básica
// ou que você irá configurá-lo conforme necessário. Por ora, vamos comentar as chamadas ao logger
// se ele depender de uma conexão que não está sendo estabelecida aqui.
// Se você tem um LogHelper que escreve para arquivos e não precisa de $conexao, pode instanciar normalmente.
// Exemplo: $logger = new LogHelper(null); // Ou qualquer configuração que seu LogHelper suporte sem $conexao

if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    // Se $logger estiver configurado:
    // $logger->log('SECURITY_WARNING', 'Tentativa de download de backup não autenticada.');
    header("HTTP/1.1 401 Unauthorized");
    die("Acesso não autorizado.");
}
$userIdLogado = $_SESSION['usuario_id'] ?? 'N/A';

// Adicionar verificação de role de administrador se necessário:
// if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
//     // Se $logger estiver configurado:
//     // $logger->log('SECURITY_WARNING', 'Tentativa de download de backup por usuário não admin.', ['user_id' => $userIdLogado]);
//     header("HTTP/1.1 403 Forbidden");
//     die("Permissão negada.");
// }


if (isset($_GET['file'])) {
    $fileName = basename($_GET['file']); // basename() para segurança, evitar path traversal
    $backupFolder = __DIR__ . DIRECTORY_SEPARATOR . 'backups' . DIRECTORY_SEPARATOR;
    $filePath = $backupFolder . $fileName;

    // Validações de segurança adicionais
    // Garante que o arquivo está dentro da pasta de backups esperada e realmente existe
    if (strpos(realpath($filePath), realpath($backupFolder)) !== 0 || !file_exists($filePath)) {
        // Se $logger estiver configurado:
        // $logger->log('SECURITY_WARNING', 'Tentativa de path traversal ou arquivo inexistente no download de backup.', ['requested_file' => $_GET['file'], 'calculated_path' => $filePath, 'user_id' => $userIdLogado]);
        header("HTTP/1.1 403 Forbidden");
        die("Acesso ao arquivo negado ou arquivo não encontrado.");
    }

    // Atualizar a regex para corresponder a arquivos .sql gerados pelo mysqldump
    // Exemplo: simposto_backup_20231027_103045.sql
    if (preg_match('/^([a-zA-Z0-9_.-]+)_backup_\d{8}_\d{6}\.sql$/', $fileName)) {
        // Se $logger estiver configurado:
        // $logger->log('INFO', 'Iniciando download de backup MySQL.', ['file' => $fileName, 'user_id' => $userIdLogado]);

        // Limpar qualquer saída anterior para evitar corrupção do arquivo
        if (ob_get_level()) {
            ob_end_clean();
        }

        header('Content-Description: File Transfer');
        header('Content-Type: application/sql'); // Mime type para arquivos .sql
        // Ou usar 'application/octet-stream' para forçar o download de forma genérica
        // header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $fileName . '"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($filePath));

        // Envia o arquivo para o navegador
        readfile($filePath);

        // Opcional: Deletar o arquivo após o download
        // if (unlink($filePath)) {
        //     // Se $logger estiver configurado:
        //     // $logger->log('INFO', 'Arquivo de backup MySQL removido após download.', ['file' => $fileName, 'user_id' => $userIdLogado]);
        // } else {
        //     // Se $logger estiver configurado:
        //     // $logger->log('WARNING', 'Falha ao remover arquivo de backup MySQL após download.', ['file' => $fileName, 'user_id' => $userIdLogado]);
        // }
        exit;
    } else {
        // Se $logger estiver configurado:
        // $logger->log('WARNING', 'Tentativa de download de arquivo de backup MySQL inválido ou não encontrado (formato do nome).', ['requested_file' => $_GET['file'], 'path_checked' => $filePath, 'user_id' => $userIdLogado]);
        header("HTTP/1.1 404 Not Found");
        die("Nome de arquivo de backup inválido ou arquivo não encontrado.");
    }
} else {
    // Se $logger estiver configurado:
    // $logger->log('WARNING', 'Tentativa de acesso a download_backup_file.php sem parâmetro de arquivo.', ['user_id' => $userIdLogado]);
    header("HTTP/1.1 400 Bad Request");
    die("Nenhum arquivo especificado para download.");
}

// Fechar conexão mysqli se ela foi aberta (ex: se LogHelper a usou)
// if (isset($conexao) && $conexao) {
//     mysqli_close($conexao);
// }
