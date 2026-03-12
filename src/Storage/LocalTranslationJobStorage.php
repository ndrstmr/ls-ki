<?php

declare(strict_types=1);

namespace Ndrstmr\LsKi\Storage;

/**
 * Filesystem-backed storage for queued and completed translation jobs.
 */
final class LocalTranslationJobStorage implements TranslationJobStorageInterface
{
    public function __construct(
        private readonly string $storageInbox,
        private readonly string $storageOutbox,
    ) {}

    public function queueJobInput(string $jobId, string $inputText): string
    {
        $this->ensureDirectory($this->storageInbox);

        $inputFile = $this->inputFilePath($jobId);
        $this->writeFile($inputFile, $inputText);

        return $inputFile;
    }

    public function isJobQueued(string $jobId): bool
    {
        return is_file($this->inputFilePath($jobId));
    }

    public function getCompletedJob(string $jobId): ?CompletedJob
    {
        $metaFile = $this->metaFilePath($jobId);
        $outputFile = $this->outputFilePath($jobId);

        if (!is_file($metaFile) || !is_file($outputFile)) {
            return null;
        }

        $rawMetadata = $this->readFile($metaFile);
        $metadata = json_decode($rawMetadata, true, 512, JSON_THROW_ON_ERROR);

        if (!is_array($metadata)) {
            throw new \RuntimeException(sprintf('Invalid metadata structure for job %s', $jobId));
        }

        return new CompletedJob(
            outputText: $this->readFile($outputFile),
            metadata: $metadata,
        );
    }

    public function storeCompletedJob(string $jobId, string $outputText, array $metadata): void
    {
        $this->ensureDirectory($this->storageOutbox);

        $this->writeFile($this->outputFilePath($jobId), $outputText);
        $this->writeFile(
            $this->metaFilePath($jobId),
            json_encode($metadata, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );
    }

    private function inputFilePath(string $jobId): string
    {
        return $this->storageInbox . '/' . $jobId . '_input.txt';
    }

    private function outputFilePath(string $jobId): string
    {
        return $this->storageOutbox . '/' . $jobId . '_leichte_sprache.txt';
    }

    private function metaFilePath(string $jobId): string
    {
        return $this->storageOutbox . '/' . $jobId . '_meta.json';
    }

    private function ensureDirectory(string $directory): void
    {
        if (is_dir($directory)) {
            return;
        }

        if (!mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw new \RuntimeException(sprintf('Cannot create directory: %s', $directory));
        }
    }

    private function readFile(string $path): string
    {
        $content = file_get_contents($path);
        if ($content === false) {
            throw new \RuntimeException(sprintf('Cannot read file: %s', $path));
        }

        return $content;
    }

    private function writeFile(string $path, string $content): void
    {
        $bytes = file_put_contents($path, $content);
        if ($bytes === false) {
            throw new \RuntimeException(sprintf('Cannot write file: %s', $path));
        }
    }
}
