<?php

declare(strict_types=1);

namespace App\Agent;

use App\LlmGateway\LlmGatewayInterface;
use App\Tool\ToolRegistryInterface;
use Psr\Log\LoggerInterface;

/**
 * Orchestriert den Übersetzungsprozess.
 *
 * Lädt Prompt-Templates, wählt Tools aus der ToolRegistry und
 * gibt strukturierte Ergebnisse zurück.
 *
 * Referenz: Anforderungsdokument Abschnitt 4.2.2
 */
final class TranslationAgent
{
    public function __construct(
        private readonly LlmGatewayInterface $llmGateway,
        private readonly ToolRegistryInterface $toolRegistry,
        private readonly LoggerInterface $logger,
        private readonly string $promptVersion,
        private readonly string $promptDir,
    ) {}

    /**
     * Übersetzt einen Verwaltungstext in Leichte Sprache.
     *
     * @return TranslationResult
     */
    public function translate(string $inputText, string $jobId): TranslationResult
    {
        $this->logger->info('TranslationAgent: Starte Übersetzung', [
            'job_id' => $jobId,
            'input_chars' => strlen($inputText),
            'model' => $this->llmGateway->getActiveModel(),
        ]);

        $systemPrompt = $this->loadPrompt('translate.txt');

        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $inputText],
        ];

        $llmResponse = $this->llmGateway->complete($messages, [
            'temperature' => 0.3,
            'max_tokens' => 4096,
        ]);

        $this->logger->info('TranslationAgent: Übersetzung abgeschlossen', [
            'job_id' => $jobId,
            'output_chars' => strlen($llmResponse->content),
            'processing_ms' => $llmResponse->processingTimeMs,
        ]);

        return new TranslationResult(
            jobId: $jobId,
            inputText: $inputText,
            outputText: $llmResponse->content,
            model: $llmResponse->model,
            inputTokens: $llmResponse->inputTokens,
            outputTokens: $llmResponse->outputTokens,
            processingTimeMs: $llmResponse->processingTimeMs,
            promptVersion: $this->promptVersion,
        );
    }

    private function loadPrompt(string $filename): string
    {
        $path = rtrim($this->promptDir, '/') . '/' . $this->promptVersion . '/' . $filename;

        if (!file_exists($path)) {
            throw new \RuntimeException(sprintf('Prompt-Template nicht gefunden: %s', $path));
        }

        return file_get_contents($path);
    }
}
