<?php

/**
 * Einfacher Ollama-API-Client (ohne Streaming).
 * Verwendung: require_once 'OllamaClient.php';
 */
class OllamaClient
{
    private string $baseUrl;
    private string $model;
    private ?array $lastResponse = null;

    public function __construct(string $baseUrl = 'http://localhost:11434', string $model = 'llama2')
    {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->model = $model;
    }

    /**
     * Prompt und optionalen Kontext absenden, Antwort komplett abholen (nicht streamen).
     *
     * @param string      $prompt        Der Benutzer-Prompt
     * @param string|null $systemContext Optional: System-/Kontext-Text (z. B. Anweisungen für das Modell)
     * @param array|null  $context       Optional: Kontext-Array von vorheriger Antwort (für Mehrfach-Dialoge)
     * @return array Dekodierte API-Antwort (z. B. 'response', 'context', 'done', 'model', ...)
     * @throws RuntimeException bei HTTP- oder API-Fehlern
     */
    public function generate(string $prompt, ?string $systemContext = null, ?array $context = null): array
    {
        $body = [
            'model'  => $this->model,
            'prompt' => $prompt,
            'stream' => false,
        ];

        if ($systemContext !== null && $systemContext !== '') {
            $body['system'] = $systemContext;
        }

        if ($context !== null && $context !== []) {
            $body['context'] = $context;
        }
        #print_r($body);echo "\n";

        $this->lastResponse = $this->request('/api/generate', $body);
        return $this->lastResponse;
    }

    /**
     * Nur den generierten Text der letzten Antwort.
     */
    public function getResponseText(): ?string
    {
        return $this->lastResponse['response'] ?? null;
    }

    /**
     * Kontext der letzten Antwort (für nächsten Aufruf bei Mehrfach-Dialogen).
     */
    public function getResponseContext(): ?array
    {
        return $this->lastResponse['context'] ?? null;
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

    public function setBaseUrl(string $baseUrl): void
    {
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    private function request(string $path, array $body): array
    {
        $url = $this->baseUrl . $path;
        $json = json_encode($body, JSON_THROW_ON_ERROR);

        $ctx = stream_context_create([
            'http' => [
                'method'  => 'POST',
                'header'  => "Content-Type: application/json\r\nContent-Length: " . strlen($json),
                'content' => $json,
                'timeout' => 300.0,
            ],
        ]);

        $raw = @file_get_contents($url, false, $ctx);

        if ($raw === false) {
            $err = error_get_last();
            throw new RuntimeException('Ollama-Anfrage fehlgeschlagen: ' . ($err['message'] ?? 'Unbekannter Fehler'));
        }

        $response = json_decode($raw, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException('Ungültige Ollama-Antwort: ' . json_last_error_msg());
        }

        if (isset($response['error'])) {
            throw new RuntimeException('Ollama API Fehler: ' . $response['error']);
        }

        return $response;
    }
}
