<?php

declare(strict_types=1);

namespace Ndrstmr\LsKi\Storage;

/**
 * Immutable Read-Model für einen abgeschlossenen Übersetzungsjob.
 */
final readonly class CompletedJob
{
    /**
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public string $outputText,
        public array $metadata,
    ) {}
}
