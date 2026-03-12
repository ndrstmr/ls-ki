<?php

declare(strict_types=1);

namespace Ndrstmr\LsKi\Storage;

interface TranslationJobStorageInterface
{
    public function queueJobInput(string $jobId, string $inputText): string;

    public function isJobQueued(string $jobId): bool;

    public function getCompletedJob(string $jobId): ?CompletedJob;

    /**
     * @param array<string, mixed> $metadata
     */
    public function storeCompletedJob(string $jobId, string $outputText, array $metadata): void;
}
