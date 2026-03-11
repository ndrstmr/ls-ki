<?php

declare(strict_types=1);

namespace Ndrstmr\LsKi\Agent;

use DateTimeImmutable;
use DateTimeInterface;

/**
 * Strukturiertes Ergebnis einer Übersetzung.
 * Wird von TranslationAgent zurückgegeben und für Metadaten-Output genutzt.
 */
final readonly class TranslationResult
{
    public DateTimeImmutable $createdAt;

    public function __construct(
        public string $jobId,
        public string $inputText,
        public string $outputText,
        public string $model,
        public int $inputTokens,
        public int $outputTokens,
        public int $processingTimeMs,
        public string $promptVersion,
        ?DateTimeImmutable $createdAt = null,
        public ?array $qualityCheck = null,
    ) {
        $this->createdAt = $createdAt ?? new DateTimeImmutable();
    }

    /**
     * Serialisiert das Ergebnis als Metadaten-Array (für .json Output-Datei).
     * Referenz: Anforderungsdokument Abschnitt 4.4
     */
    public function toMetadata(string $sourceFile = ''): array
    {
        return [
            'job_id' => $this->jobId,
            'source_file' => $sourceFile,
            'timestamp' => $this->createdAt->format(DateTimeInterface::ATOM),
            'model' => [
                'name' => $this->model,
                'provider' => 'vllm-local',
            ],
            'input' => [
                'char_count' => strlen($this->inputText),
                'word_count' => str_word_count($this->inputText),
            ],
            'output' => [
                'char_count' => strlen($this->outputText),
                'word_count' => str_word_count($this->outputText),
                'processing_time_ms' => $this->processingTimeMs,
            ],
            'quality_check' => $this->qualityCheck,
            'prompt_version' => $this->promptVersion,
        ];
    }
}
