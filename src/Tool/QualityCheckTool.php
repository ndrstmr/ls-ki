<?php

declare(strict_types=1);

namespace Ndrstmr\LsKi\Tool;

use Ndrstmr\LsKi\LlmGateway\LlmGatewayInterface;
use Psr\Log\LoggerInterface;

/**
 * QualityCheckTool – prüft Leichte-Sprache-Texte gegen das Regelwerk.
 *
 * Wird als opt-in Tool aufgerufen wenn quality_check=true im Request.
 * Nutzt den quality-check.txt Prompt und gibt Score + Issues zurück.
 */
final class QualityCheckTool implements ToolInterface
{
    public function __construct(
        private readonly LlmGatewayInterface $llmGateway,
        private readonly LoggerInterface $logger,
        private readonly string $promptDir,
        private readonly string $promptVersion,
    ) {}

    public function getName(): string
    {
        return 'quality_check';
    }

    public function getDescription(): string
    {
        return 'Prüft einen Leichte-Sprache-Text gegen das Regelwerk und gibt Score + Verbesserungshinweise zurück.';
    }

    /**
     * @param array{text: string} $input
     * @return array{score: int, issues: array<mixed>, summary: string}
     */
    public function execute(array $input): mixed
    {
        $text = $input['text'] ?? '';

        if ($text === '') {
            return ['score' => 0, 'issues' => [], 'summary' => 'Kein Text übergeben.'];
        }

        $promptPath = rtrim($this->promptDir, '/') . '/' . $this->promptVersion . '/quality-check.txt';

        if (!file_exists($promptPath)) {
            $this->logger->error('QualityCheckTool: Prompt nicht gefunden', ['path' => $promptPath]);
            return ['score' => 0, 'issues' => [], 'summary' => 'Qualitätsprüfung fehlgeschlagen: Prompt nicht gefunden.'];
        }

        $systemPrompt = file_get_contents($promptPath);
        if ($systemPrompt === false) {
            return ['score' => 0, 'issues' => [], 'summary' => 'Qualitätsprüfung fehlgeschlagen: Prompt nicht lesbar.'];
        }

        try {
            $response = $this->llmGateway->complete([
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $text],
            ], [
                'temperature' => 0.1,
                'max_tokens' => 1024,
            ]);

            $content = trim($response->content);

            // JSON-Extraktion: Modelle schreiben manchmal ```json ... ``` drum herum
            if (preg_match('/\{.*\}/s', $content, $matches)) {
                $content = $matches[0];
            }

            $result = json_decode($content, true);

            if (json_last_error() !== JSON_ERROR_NONE || !is_array($result)) {
                $this->logger->warning('QualityCheckTool: JSON-Parse-Fehler', [
                    'error' => json_last_error_msg(),
                    'content' => substr($content, 0, 200),
                ]);
                return ['score' => 0, 'issues' => [], 'summary' => 'Qualitätsprüfung fehlgeschlagen: Ungültige Antwort vom Modell.'];
            }

            return [
                'score' => (int) ($result['score'] ?? 0),
                'issues' => (array) ($result['issues'] ?? []),
                'summary' => (string) ($result['summary'] ?? ''),
            ];
        } catch (\Throwable $e) {
            $this->logger->error('QualityCheckTool: LLM-Aufruf fehlgeschlagen', ['error' => $e->getMessage()]);
            return ['score' => 0, 'issues' => [], 'summary' => 'Qualitätsprüfung fehlgeschlagen: ' . $e->getMessage()];
        }
    }
}
