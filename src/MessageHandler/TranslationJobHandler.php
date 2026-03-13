<?php

declare(strict_types=1);

namespace Ndrstmr\LsKi\MessageHandler;

use Ndrstmr\LsKi\Agent\TranslationAgent;
use Ndrstmr\LsKi\Message\TranslationJobMessage;
use Ndrstmr\LsKi\Storage\TranslationJobStorageInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Verarbeitet asynchrone Übersetzungsjobs aus der Messenger-Queue.
 *
 * Ablauf:
 *   1. Input-Datei lesen
 *   2. TranslationAgent aufrufen
 *   3. Übersetzung + Metadaten in Outbox schreiben
 */
#[AsMessageHandler]
final class TranslationJobHandler
{
    public function __construct(
        private readonly TranslationAgent $translationAgent,
        private readonly TranslationJobStorageInterface $jobStorage,
        private readonly LoggerInterface $logger,
    ) {}

    public function __invoke(TranslationJobMessage $message): void
    {
        $this->logger->info('TranslationJobHandler: Job gestartet', [
            'job_id' => $message->jobId,
            'input_file' => $message->inputFilePath,
        ]);

        if (!is_file($message->inputFilePath)) {
            throw new \RuntimeException(sprintf('Input-Datei nicht gefunden: %s', $message->inputFilePath));
        }

        $inputText = file_get_contents($message->inputFilePath);
        if ($inputText === false) {
            throw new \RuntimeException(sprintf('Input-Datei kann nicht gelesen werden: %s', $message->inputFilePath));
        }

        $sourceFile = basename($message->inputFilePath);
        $result = $this->translationAgent->translate($inputText, $message->jobId);

        $this->jobStorage->storeCompletedJob(
            jobId: $message->jobId,
            outputText: $result->outputText,
            metadata: $result->toMetadata($sourceFile),
        );

        $this->logger->info('TranslationJobHandler: Job abgeschlossen', [
            'job_id' => $message->jobId,
        ]);
    }
}
