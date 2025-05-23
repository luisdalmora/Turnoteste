<?php
// gerador_senhas.php
require_once __DIR__ . '/config.php'; // Inclui configurações e inicia sessão

// Verificar se o usuário está logado
if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Sessão expirada ou acesso negado.', 'action' => 'redirect', 'location' => 'index.html']);
        exit;
    }
    header('Location: index.html?erro=' . urlencode('Acesso negado. Faça login primeiro.'));
    exit;
}

$nomeUsuarioLogado = $_SESSION['usuario_nome_completo'] ?? 'Usuário';
// Se precisar de um token CSRF específico para esta página, gere aqui:
// if (empty($_SESSION['csrf_token_gerador_senhas'])) {
// $_SESSION['csrf_token_gerador_senhas'] = bin2hex(random_bytes(32));
// }
// $csrfTokenGeradorSenhas = $_SESSION['csrf_token_gerador_senhas'];

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerador de Senhas - Sim Posto</title>
    <link href="src/output.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Roboto+Mono:wght@700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
        <script defer src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    <style>
        /* CSS para manter a fonte Roboto Mono para a senha gerada */
        #senhaGeradaDisplay {
            font-family: "Roboto Mono", monospace;
        }
        /* Removido .password-generator-card pois agora usaremos classes Tailwind para o fundo branco */
    </style>
</head>
<body class="bg-gray-100 font-poppins text-gray-700">
    <div class="flex h-screen overflow-hidden">
        <aside class="w-64 bg-gradient-to-b from-blue-800 to-blue-700 text-indigo-100 flex flex-col flex-shrink-0">
            <div class="h-16 flex items-center px-4 md:px-6 border-b border-white/10">
                <i data-lucide="gauge-circle" class="w-7 h-7 md:w-8 md:h-8 mr-2 md:mr-3 text-white"></i>
                <h2 class="text-lg md:text-xl font-semibold text-white">Sim Posto</h2>
            </div>
            <nav class="flex-grow p-2 space-y-1">
                <a href="home.php" class="flex items-center px-3 py-2.5 rounded-lg hover:bg-blue-500 hover:text-white transition-colors text-sm">
                    <i data-lucide="layout-dashboard" class="w-5 h-5 mr-3"></i> Dashboard
                </a>
                <a href="relatorio_turnos.php" class="flex items-center px-3 py-2.5 rounded-lg hover:bg-blue-500 hover:text-white transition-colors text-sm">
                    <i data-lucide="file-text" class="w-5 h-5 mr-3"></i> Relatórios
                </a>
                <a href="gerenciar_colaboradores.php" class="flex items-center px-3 py-2.5 rounded-lg hover:bg-blue-500 hover:text-white transition-colors text-sm">
                    <i data-lucide="users" class="w-5 h-5 mr-3"></i> Colaboradores
                </a>
                <a href="gerador_senhas.php" class="flex items-center px-3 py-2.5 rounded-lg bg-blue-600 text-white font-medium text-sm"> <i data-lucide="key-round" class="w-5 h-5 mr-3"></i> Gerador de Senhas
                </a>
            </nav>
            <div class="p-2 border-t border-white/10">
                 <div class="px-2 py-1 space-y-1.5">
                    <?php
                    // Exemplo: Botão de Backup, se você tiver o token CSRF para ele
                    if (isset($_SESSION['csrf_token_backup'])) {
                        echo '<input type="hidden" id="csrf-token-backup" value="' . htmlspecialchars($_SESSION['csrf_token_backup']) . '">';
                        echo '<a href="#" id="backup-db-btn" class="flex items-center justify-center w-full px-3 py-2 rounded-lg bg-teal-500 hover:bg-teal-600 text-white font-medium transition-colors text-sm">';
                        echo '<i data-lucide="database-backup" class="w-4 h-4 mr-2"></i> Backup BD';
                        echo '</a>';
                    }
                    ?>
                </div>
                <div class="px-2 py-1 mt-1.5">
                    <a href="logout.php" id="logout-link" class="flex items-center justify-center w-full px-3 py-2 rounded-lg bg-red-500 hover:bg-red-600 text-white font-medium transition-colors text-sm">
                        <i data-lucide="log-out" class="w-4 h-4 mr-2"></i> Sair
                    </a>
                </div>
            </div>
        </aside>

        <div class="flex-grow flex flex-col overflow-y-auto">
            <header class="h-16 bg-white shadow-sm flex items-center justify-between px-4 md:px-6 flex-shrink-0">
                <div class="flex items-center">
                    <i data-lucide="key-round" class="w-6 h-6 md:w-7 md:h-7 mr-2 md:mr-3 text-blue-600"></i>
                    <h1 class="text-md md:text-lg font-semibold text-gray-800">Gerador de Senhas</h1>
                </div>
                <div id="user-info" class="flex items-center text-sm font-medium text-gray-700">
                    Olá, <?php echo htmlspecialchars($nomeUsuarioLogado); ?>
                    <i data-lucide="circle-user-round" class="w-5 h-5 md:w-6 md:h-6 ml-2 text-blue-600"></i>
                </div>
            </header>

            <main class="flex-grow p-4 md:p-6 flex items-center justify-center bg-gray-100">
                <section class="bg-white p-6 md:p-8 rounded-lg shadow-lg w-full max-w-md text-center">
                    <h2 class="text-xl font-semibold text-gray-800 mb-6">Gerar Senha</h2>

                    <button id="senhaPay" class="w-full mb-3 flex items-center justify-center px-4 py-2.5 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition duration-150 ease-in-out">
                        <i data-lucide="credit-card" class="w-4 h-4 mr-2"></i> Senha Pay
                    </button>
                    <button id="senhaAutomacao" class="w-full mb-6 flex items-center justify-center px-4 py-2.5 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition duration-150 ease-in-out">
                        <i data-lucide="zap" class="w-4 h-4 mr-2"></i> Senha Automação
                    </button>

                    <div id="senhaGeradaDisplayContainer" class="mb-6 hidden py-4">
                        <p id="senhaGeradaDisplay" class="text-blue-600 text-4xl md:text-5xl font-bold break-all"></p>
                    </div>

                    <button id="copiarSenha" class="w-full flex items-center justify-center px-4 py-2.5 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 transition duration-150 ease-in-out hidden">
                        <i data-lucide="copy" class="w-4 h-4 mr-2"></i> Copiar Senha
                    </button>
                </section>
            </main>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
    <script src="src/js/gerador_senhas_script.js"></script>
    <script src="src/js/main.js" type="module"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
            // Configuração do Toastr (pode já estar no seu main.js)
            toastr.options = {
                "closeButton": true,
                "progressBar": true,
                "positionClass": "toast-top-right",
                "preventDuplicates": true,
                "timeOut": "3000"
            };
        });
    </script>
</body>
</html>