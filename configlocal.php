<?php
// config.php

if (session_status() == PHP_SESSION_NONE) {
    $cookieParams = [
        'lifetime' => 0,
        'path' => '/',
        'secure' => isset($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Lax'
    ];
    session_set_cookie_params($cookieParams);
    session_start();
}

// --- Configurações do Google API ---
// Constantes OAuth2 não são mais necessárias para o escopo atual (apenas feriados públicos)
// define('GOOGLE_CLIENT_ID', 'SEU_CLIENT_ID.apps.googleusercontent.com');
// define('GOOGLE_CLIENT_SECRET', 'SEU_CLIENT_SECRET');
// define('GOOGLE_REDIRECT_URI', 'http://localhost/turno/google_oauth_callback.php');
// PATH_TO_CLIENT_SECRET_JSON pode não ser mais necessário se usar apenas API Key.
// define('PATH_TO_CLIENT_SECRET_JSON', __DIR__ . '/client_secret.json');

define('GOOGLE_APPLICATION_NAME', 'Sim Posto Gestao de Turnos');
// Adicione sua Chave de API do Google Cloud Console aqui.
// Certifique-se de que a API do Google Calendar está habilitada para esta chave.
define('GOOGLE_API_KEY', 'AIzaSyC3zC01xyp9BIKssCp_EFmFzceKKFdaFro'); // Substitua pela sua chave de API

// --- Configurações de E-mail (se for usar o EmailHelper.php) ---
define('EMAIL_FROM_ADDRESS', 'postosim8@gmail.com');
define('EMAIL_FROM_NAME', 'Sim Posto Sistema');

// --- Configurações Gerais ---
define('SITE_URL', (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'] . "/turno");

// --- Configurações de Erro ---
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
// Em produção:
// ini_set('display_errors', 0);
// ini_set('log_errors', 1);
// ini_set('error_log', __DIR__ . '/php_errors.log');
