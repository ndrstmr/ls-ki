<?php

declare(strict_types=1);

namespace Ndrstmr\LsKi\Storage;

interface TranslationJobStorageInterface
{
    /**
     * Schreibt den Input-Text in die Inbox und gibt den Dateipfad zurück.
     */
    public function queueJobInput(string $jobId, string $inputText): string;

    /**
     * Prüft ob ein Job noch in der Inbox liegt (= in Verarbeitung).
     */
    public function isJobQueued(string $jobId): bool;

    /**
     * Gibt einen abgeschlossenen Job zurück, oder null wenn noch nicht fertig.
     */
    public function getCompletedJob(string $jobId): ?CompletedJob;

    /**
     * Persistiert das Ergebnis eines abgeschlossenen Jobs in der Outbox.
     *
     * @param array<string, mixed> $metadata
     */
    public function storeCompletedJob(string $jobId, string $outputText, array $metadata): void;
}
