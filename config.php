<?php
// config.php (Adaptado para Vercel)

if (session_status() == PHP_SESSION_NONE) {
    $cookieParams = [
        // O lifetime 0 significa que o cookie dura até o navegador ser fechado.
        // Para persistência entre sessões de navegador, você precisaria de um valor maior (em segundos).
        // No entanto, com serverless, a gestão de sessão via arquivos pode ser instável.
        'lifetime' => getenv('APP_ENV') === 'production' ? 1800 : 0, // 30 minutos em produção, 0 para local
        'path' => '/',
        // Em produção na Vercel, o HTTPS é gerenciado pela Vercel.
        'secure' => (getenv('APP_ENV') === 'production' || (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on')),
        'httponly' => true,
        'samesite' => 'Lax'
    ];
    session_set_cookie_params($cookieParams);
    session_start();
}

// --- Configurações Gerais e URLs ---
$protocol = 'https'; // Vercel usa HTTPS por padrão para a URL de produção.
                    // Para desenvolvimento local, você pode querer verificar $_SERVER['HTTPS']

$vercelUrl = getenv('VERCEL_URL'); // Ex: meu-projeto-sufixo.vercel.app

if (getenv('APP_ENV') === 'production' && $vercelUrl) {
    // Em produção na Vercel, SITE_URL será a URL da Vercel.
    define('SITE_URL', $protocol . '://' . $vercelUrl);
} else {
    // Fallback para desenvolvimento local ou se VERCEL_URL não estiver definida
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $baseDir = '/turno'; // Ajuste se seu projeto local não estiver em /turno
    $localProtocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https://" : "http://";
    define('SITE_URL', $localProtocol . $host . $baseDir);
}

// --- Configurações do Google API ---
define('GOOGLE_APPLICATION_NAME', 'Sim Posto Gestao de Turnos'); // Pode manter como está
define('GOOGLE_API_KEY', getenv('GOOGLE_API_KEY') ?: 'AIzaSyC3zC01xyp9BIKssCp_EFmFzceKKFdaFro'); // Obter da Vercel env

// --- Configurações de E-mail ---
define('EMAIL_FROM_ADDRESS', getenv('EMAIL_FROM_ADDRESS') ?: 'seu_email_local@example.com');
define('EMAIL_FROM_NAME', getenv('EMAIL_FROM_NAME') ?: 'Nome Local Sistema');


// --- Configurações de Erro ---
if (getenv('APP_ENV') === 'production') {
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
    ini_set('log_errors', 1);
    // Na Vercel, os logs são geralmente enviados para o sistema de logging da Vercel,
    // então a diretiva error_log para um arquivo pode não ser necessária ou funcionar como esperado.
    // error_log("PHP Error: [See Vercel Logs]", 0); // Exemplo
} else {
    // Configurações para desenvolvimento local
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
}

