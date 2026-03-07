<?php

/**
 * OpenAI-API-Client für die Chat-Schnittstelle.
 * Bietet die gleiche Schnittstelle wie OllamaClient.php.
 */
class OpenAIClient
{
    private string $apiKey;
    private string $model;
    private ?array $lastResponse = null;
    private ?array $lastHistory = null;

    public function __construct(string $apiKey, string $model = 'gpt-4o')
    {
        $this->apiKey = $apiKey;
        $this->model = $model;
    }

    /**
     * Prompt und optionalen Kontext absenden, Antwort abholen.
     *
     * @param string      $prompt        Der Benutzer-Prompt
     * @param string|null $systemContext Optional: System-/Kontext-Text (Anweisungen für das Modell)
     * @param array|null  $context       Optional: Nachrichten-Historie von vorheriger Antwort
     * @return array Dekodierte API-Antwort
     * @throws RuntimeException bei HTTP- oder API-Fehlern
     */
    public function generate(string $prompt, ?string $systemContext = null, ?array $context = null): array
    {
        $messages = [];

        // System-Nachricht hinzufügen
        if ($systemContext !== null && $systemContext !== '') {
            $messages[] = [
                'role' => 'system',
                'content' => $systemContext
            ];
        }

        // Bisherigen Verlauf hinzufügen
        if ($context !== null && is_array($context)) {
            foreach ($context as $msg) {
                if (isset($msg['role'], $msg['content'])) {
                    $messages[] = $msg;
                }
            }
        }

        // Aktuelle Benutzer-Frage hinzufügen
        $messages[] = [
            'role' => 'user',
            'content' => $prompt
        ];

        $body = [
            'model'  => $this->model,
            'messages' => $messages,
            'stream' => false,
        ];

        $this->lastResponse = $this->request('/v1/chat/completions', $body);

        // Wir speichern den Kontext OHNE die System-Nachricht am Anfang,
        // da diese bei jedem Aufruf von generate() neu hinzugefügt wird.
        // Falls der Kontext bereits vorhanden war, führen wir ihn weiter.
        $newHistory = $context ?? [];
        $newHistory[] = ['role' => 'user', 'content' => $prompt];
        if (isset($this->lastResponse['choices'][0]['message'])) {
            $newHistory[] = $this->lastResponse['choices'][0]['message'];
        }
        $this->lastHistory = $newHistory;

        return $this->lastResponse;
    }

    /**
     * Nur den generierten Text der letzten Antwort.
     */
    public function getResponseText(): ?string
    {
        return $this->lastResponse['choices'][0]['message']['content'] ?? null;
    }

    /**
     * Kontext der letzten Antwort (für nächsten Aufruf bei Mehrfach-Dialogen).
     * Gibt bei OpenAI das Nachrichten-Array zurück.
     */
    public function getResponseContext(): ?array
    {
        return $this->lastHistory;
    }

    /**
     * Letzte volle API-Antwort.
     */
    public function getLastResponse(): ?array
    {
        return $this->lastResponse;
    }

    public function setModel(string $model): void
    {
        $this->model = $model;
    }

    public function getModel(): string
    {
        return $this->model;
    }

    private function request(string $path, array $body): array
    {
        $url = 'https://api.openai.com' . $path;
        $json = json_encode($body, JSON_THROW_ON_ERROR);

        $ctx = stream_context_create([
            'http' => [
                'method'  => 'POST',
                'header'  => "Content-Type: application/json\r\n" .
                             "Authorization: Bearer " . $this->apiKey . "\r\n" .
                             "Content-Length: " . strlen($json),
                'content' => $json,
                'timeout' => 120.0,
            ],
        ]);

        $raw = @file_get_contents($url, false, $ctx);

        if ($raw === false) {
            $err = error_get_last();
            throw new RuntimeException('OpenAI-Anfrage fehlgeschlagen: ' . ($err['message'] ?? 'Unbekannter Fehler'));
        }

        $response = json_decode($raw, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException('Ungültige OpenAI-Antwort: ' . json_last_error_msg());
        }

        if (isset($response['error'])) {
            throw new RuntimeException('OpenAI API Fehler: ' . $response['error']['message']);
        }

        return $response;
    }
}
