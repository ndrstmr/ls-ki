<?php

declare(strict_types=1);

namespace App\LlmGateway;

use Psr\Log\LoggerInterface;

/**
 * Mock-Implementierung des LlmGateway für lokale Entwicklung ohne GPU.
 *
 * Simuliert die vLLM-API und gibt deterministische Test-Antworten zurück.
 * Aktivierung: LLM_PROVIDER=mock in .env.local
 *
 * Auf der A100-VM wird stattdessen VllmGateway verwendet (LLM_PROVIDER=vllm).
 */
final class MockLlmGateway implements LlmGatewayInterface
{
    private string $activeModel;

    /** @var string[] */
    private array $availableModels = [
        'casperhansen/llama-3.3-70b-instruct-awq',
        'mistralai/Mistral-Small-3.1-24B-Instruct-2503',
    ];

    public function __construct(
        private readonly LoggerInterface $logger,
        string $activeModel,
    ) {
        $this->activeModel = $activeModel;
    }

    public function complete(array $messages, array $options = []): LlmResponse
    {
        $start = hrtime(true);

        $userMessage = $this->extractLastUserMessage($messages);
        $mockResponse = $this->generateMockResponse($userMessage);

        // Simuliere eine kurze Verarbeitungszeit
        usleep(200_000); // 200ms

        $processingTimeMs = (int) ((hrtime(true) - $start) / 1_000_000);

        $this->logger->info('MockLlmGateway: Anfrage verarbeitet', [
            'model' => $this->activeModel,
            'input_chars' => strlen($userMessage),
            'output_chars' => strlen($mockResponse),
        ]);

        return new LlmResponse(
            content: $mockResponse,
            model: $this->activeModel,
            inputTokens: (int) (strlen($userMessage) / 4), // grobe Schätzung
            outputTokens: (int) (strlen($mockResponse) / 4),
            processingTimeMs: $processingTimeMs,
        );
    }

    public function getActiveModel(): string
    {
        return $this->activeModel;
    }

    public function switchModel(string $modelId): void
    {
        if (!in_array($modelId, $this->availableModels, true)) {
            throw new \InvalidArgumentException(
                sprintf('Modell "%s" ist nicht verfügbar. Verfügbar: %s', $modelId, implode(', ', $this->availableModels))
            );
        }

        $this->logger->info('MockLlmGateway: Modell gewechselt', [
            'from' => $this->activeModel,
            'to' => $modelId,
        ]);

        $this->activeModel = $modelId;
    }

    public function getAvailableModels(): array
    {
        return $this->availableModels;
    }

    private function extractLastUserMessage(array $messages): string
    {
        foreach (array_reverse($messages) as $message) {
            if ($message['role'] === 'user') {
                return $message['content'];
            }
        }

        return '';
    }

    private function generateMockResponse(string $input): string
    {
        // Gibt eine strukturierte Mock-Übersetzung zurück, die das Format der echten
        // Leichte-Sprache-Ausgabe simuliert – nützlich für Frontend/API-Tests.
        $wordCount = str_word_count($input);

        return <<<MOCK
            [MOCK-ÜBERSETZUNG – Modell: {$this->activeModel}]

            Das ist eine Test-Übersetzung in Leichte Sprache.

            Der Original-Text hatte ungefähr {$wordCount} Wörter.

            Hier sind die Regeln der Leichten Sprache:
            - Kurze Sätze sind wichtig.
            - Jeder Satz hat eine Aussage.
            - Schwere Wörter werden erklärt.
            - Es gibt keine Abkürzungen.

            Diese Übersetzung kommt vom Mock-Provider.
            Auf der A100-VM kommt die echte Übersetzung vom LLM.
            MOCK;
    }
}
