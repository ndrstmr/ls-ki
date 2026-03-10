<?php

declare(strict_types=1);

namespace Ndrstmr\LsKi\LlmGateway;

/**
 * Normalisierte Antwort vom LLM-Provider.
 * Entkoppelt die Applikationslogik von provider-spezifischen Antwortformaten.
 */
final readonly class LlmResponse
{
    public function __construct(
        public string $content,
        public string $model,
        public int $inputTokens,
        public int $outputTokens,
        public int $processingTimeMs,
    ) {}
}
