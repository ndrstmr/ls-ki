<?php

declare(strict_types=1);

namespace Ndrstmr\LsKi\LlmGateway;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * vLLM-Implementierung des LlmGateway für Produktion auf der A100-VM.
 *
 * Kommuniziert mit dem vLLM-Container über die OpenAI-kompatible API
 * im internen Docker-Netzwerk (http://vllm:8000).
 *
 * Aktivierung: LLM_PROVIDER=vllm in .env (auf der VM)
 *
 * Referenz: Infrastrukturkonzept Abschnitt 3, Anforderungsdokument 4.1.1
 */
final class VllmGateway implements LlmGatewayInterface
{
    private string $activeModel;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        private readonly string $apiUrl,
        private readonly string $apiKey,
        string $activeModel,
    ) {
        $this->activeModel = $activeModel;
    }

    public function complete(array $messages, array $options = []): LlmResponse
    {
        $start = hrtime(true);

        $payload = [
            'model' => $this->activeModel,
            'messages' => $messages,
            'temperature' => $options['temperature'] ?? 0.3,
            'max_tokens' => $options['max_tokens'] ?? 4096,
        ];

        $headers = ['Content-Type' => 'application/json'];
        if ($this->apiKey !== '') {
            $headers['Authorization'] = 'Bearer ' . $this->apiKey;
        }

        try {
            $response = $this->httpClient->request('POST', $this->apiUrl . '/v1/chat/completions', [
                'headers' => $headers,
                'json' => $payload,
                'timeout' => 300, // 5 Min – LLM-Inference kann lange dauern
            ]);

            $data = $response->toArray();
        } catch (\Throwable $e) {
            $this->logger->error('VllmGateway: Anfrage fehlgeschlagen', [
                'error' => $e->getMessage(),
                'model' => $this->activeModel,
                'api_url' => $this->apiUrl,
            ]);
            throw new \RuntimeException('LLM-Anfrage fehlgeschlagen: ' . $e->getMessage(), previous: $e);
        }

        $processingTimeMs = (int) ((hrtime(true) - $start) / 1_000_000);

        $content = $data['choices'][0]['message']['content'] ?? '';
        $usage = $data['usage'] ?? [];

        $this->logger->info('VllmGateway: Anfrage erfolgreich', [
            'model' => $this->activeModel,
            'input_tokens' => $usage['prompt_tokens'] ?? 0,
            'output_tokens' => $usage['completion_tokens'] ?? 0,
            'processing_ms' => $processingTimeMs,
        ]);

        return new LlmResponse(
            content: $content,
            model: $this->activeModel,
            inputTokens: $usage['prompt_tokens'] ?? 0,
            outputTokens: $usage['completion_tokens'] ?? 0,
            processingTimeMs: $processingTimeMs,
        );
    }

    public function getActiveModel(): string
    {
        return $this->activeModel;
    }

    public function switchModel(string $modelId): void
    {
        // Verifiziere, dass das Modell im vLLM-Server verfügbar ist
        $available = $this->getAvailableModels();

        if (!in_array($modelId, $available, true)) {
            throw new \InvalidArgumentException(
                sprintf('Modell "%s" nicht in vLLM verfügbar. Verfügbar: %s', $modelId, implode(', ', $available))
            );
        }

        $this->logger->info('VllmGateway: Modell gewechselt', [
            'from' => $this->activeModel,
            'to' => $modelId,
        ]);

        $this->activeModel = $modelId;
    }

    public function getAvailableModels(): array
    {
        try {
            $response = $this->httpClient->request('GET', $this->apiUrl . '/v1/models', [
                'timeout' => 10,
            ]);

            $data = $response->toArray();

            return array_column($data['data'] ?? [], 'id');
        } catch (\Throwable $e) {
            $this->logger->warning('VllmGateway: Modell-Liste konnte nicht abgerufen werden', [
                'error' => $e->getMessage(),
            ]);

            return [$this->activeModel];
        }
    }
}
