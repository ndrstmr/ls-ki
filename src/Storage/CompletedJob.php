<?php

declare(strict_types=1);

namespace Ndrstmr\LsKi\Storage;

/**
 * Immutable read model for a completed translation job.
 */
readonly final class CompletedJob
{
    /**
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public string $outputText,
        public array $metadata,
    ) {}
}
