<?php
// GoogleCalendarHelper.php (Simplificado para apenas listar eventos de calendários públicos)

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/config.php'; // Para GOOGLE_APPLICATION_NAME e, potencialmente, GOOGLE_API_KEY

use Google\Client as GoogleClient;
use Google\Service\Calendar as GoogleServiceCalendar;
// Event, EventDateTime, GoogleServiceException, GuzzleRequestException não são mais necessários para esta versão simplificada.

class GoogleCalendarHelper {
    private $client;
    private $logger;
    // $conexao_db não é mais necessário aqui, pois não salvaremos tokens de usuário.

    public function __construct(LogHelper $logger) { // Removida a dependência $db_connection
        $this->logger = $logger;

        $this->client = new GoogleClient();
        $this->client->setApplicationName(GOOGLE_APPLICATION_NAME);

        // Para acessar dados públicos, como calendários de feriados,
        // geralmente é recomendável usar uma Chave de API.
        // Defina GOOGLE_API_KEY em seu config.php se sua biblioteca/configuração exigir.
        if (defined('GOOGLE_API_KEY') && !empty(GOOGLE_API_KEY)) {
            $this->client->setDeveloperKey(GOOGLE_API_KEY);
        } else {
            // Se não houver chave de API, o acesso a alguns calendários públicos ainda pode funcionar,
            // mas é menos garantido e pode estar sujeito a cotas mais restritas.
            // A biblioteca cliente do Google pode precisar do setAuthConfig mesmo para chaves de API,
            // ou pode funcionar sem ele para dados públicos se uma chave de API for definida.
            // Se PATH_TO_CLIENT_SECRET_JSON contiver uma chave de API em vez de credenciais OAuth2,
            // a lógica original de setAuthConfig pode ser mantida.
            // Por ora, vamos assumir que setDeveloperKey é suficiente ou que o acesso público funciona sem token.
            // Se setAuthConfig for estritamente necessário mesmo para chaves de API ou acesso público sem autenticação,
            // você pode precisar descomentar e ajustar.
            /*
            try {
                $this->client->setAuthConfig(PATH_TO_CLIENT_SECRET_JSON);
            } catch (\Google\Exception $e) {
                $this->logger->log('GCAL_CRITICAL', 'Falha ao carregar arquivo de configuração JSON do Google: ' . $e->getMessage(), ['path' => PATH_TO_CLIENT_SECRET_JSON]);
            }
            */
        }
        
        // Scopes não são necessários para acessar calendários públicos com chave de API.
        // $this->client->setScopes([GoogleServiceCalendar::CALENDAR_READONLY]); // Ou nenhum scope
    }

    public function listEventsFromCalendar($calendarId, $optParams = []) {
        // $userId não é mais necessário aqui, pois não estamos usando tokens específicos de usuário
        $service = new GoogleServiceCalendar($this->client);

        try {
            if (!$this->client instanceof GoogleClient) {
                $this->logger->log('GCAL_CRITICAL', 'Google Client não inicializado corretamente em listEventsFromCalendar.');
                return null;
            }
            $events = $service->events->listEvents($calendarId, $optParams);
            return $events->getItems();
        } catch (\Google\Service\Exception $e) { // Captura GoogleServiceException especificamente
            $this->logger->log('GCAL_ERROR', 'Google Service Exception ao LISTAR eventos de ' . $calendarId . ': ' . $e->getMessage(), [
                'calendar_id' => $calendarId, 'errors' => $e->getErrors()
            ]);
            return null;
        } catch (\Google\Exception $e) { // Captura exceções mais genéricas da biblioteca Google
             $this->logger->log('GCAL_ERROR', 'Google Library Exception ao LISTAR eventos de ' . $calendarId . ': ' . $e->getMessage(), [
                'calendar_id' => $calendarId
            ]);
            return null;
        } catch (Exception $e) { // Captura qualquer outra exceção
            $this->logger->log('GCAL_ERROR', 'Erro genérico ao LISTAR eventos de ' . $calendarId . ': ' . $e->getMessage(), [
                'calendar_id' => $calendarId, 'trace' => $e->getTraceAsString()
            ]);
            return null;
        }
    }

    // Métodos removidos:
    // getAuthUrl()
    // exchangeCodeForToken($authCode)
    // saveTokenForUser($userId, $tokenData)
    // getAccessTokenForUser($userId)
    // removeTokenFromDb($userId)
    // createEvent(...)
    // deleteEvent(...)
    // revokeTokenForUser(...)
}
