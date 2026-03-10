<?php

declare(strict_types=1);

namespace App\Tool;

use App\LlmGateway\LlmGatewayInterface;
use Psr\Log\LoggerInterface;

/**
 * QualityCheckTool – prüft Leichte-Sprache-Texte gegen das Regelwerk.
 *
 * Ruft das LLM mit dem quality-check.txt Prompt auf und gibt eine
 * strukturierte Bewertung zurück: Score (0-100), Issues, Summary.
 *
 * Dieses Tool wird automatisch in der ToolRegistry registriert
 * durch den Symfony-Tag "ls_ki.tool" (siehe services.yaml).
 */
final class QualityCheckTool implements ToolInterface
{
    public function __construct(
        private readonly LlmGatewayInterface $llmGateway,
        private readonly LoggerInterface $logger,
        private readonly string $promptDir,
        private readonly string $promptVersion,
    ) {
    }

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
        $text = $input['text'] ?? throw new \InvalidArgumentException('Parameter "text" fehlt.');

        $systemPrompt = $this->loadPrompt();

        $this->logger->info('QualityCheckTool: starte Qualitätsprüfung', [
            'text_length' => strlen($text),
            'model' => $this->llmGateway->getActiveModel(),
        ]);

        $response = $this->llmGateway->complete([
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $text],
        ], ['temperature' => 0.1]);

        $result = json_decode($response->content, true);

        if (!is_array($result) || !isset($result['score'], $result['issues'], $result['summary'])) {
            $this->logger->warning('QualityCheckTool: ungültige LLM-Antwort', [
                'raw' => $response->content,
            ]);

            return [
                'score' => 0,
                'issues' => [],
                'summary' => 'Qualitätsprüfung fehlgeschlagen – LLM-Antwort konnte nicht verarbeitet werden.',
            ];
        }

        $this->logger->info('QualityCheckTool: Prüfung abgeschlossen', [
            'score' => $result['score'],
            'issues_count' => count($result['issues']),
        ]);

        return [
            'score' => (int) $result['score'],
            'issues' => $result['issues'],
            'summary' => (string) $result['summary'],
        ];
    }

    private function loadPrompt(): string
    {
        $path = sprintf('%s/%s/quality-check.txt', $this->promptDir, $this->promptVersion);

        if (!file_exists($path)) {
            throw new \RuntimeException(sprintf('Quality-Check-Prompt nicht gefunden: %s', $path));
        }

        return trim((string) file_get_contents($path));
    }
}
