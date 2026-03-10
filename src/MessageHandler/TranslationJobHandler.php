<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Agent\TranslationAgent;
use App\Message\TranslationJobMessage;
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
        private readonly LoggerInterface $logger,
    ) {}

    public function __invoke(TranslationJobMessage $message): void
    {
        $this->logger->info('TranslationJobHandler: Job gestartet', [
            'job_id' => $message->jobId,
            'input_file' => $message->inputFilePath,
        ]);

        if (!file_exists($message->inputFilePath)) {
            throw new \RuntimeException(sprintf('Input-Datei nicht gefunden: %s', $message->inputFilePath));
        }

        $inputText = file_get_contents($message->inputFilePath);
        $sourceFile = basename($message->inputFilePath);

        $result = $this->translationAgent->translate($inputText, $message->jobId);

        // Output-Verzeichnis sicherstellen
        if (!is_dir($message->outputDir)) {
            mkdir($message->outputDir, 0755, true);
        }

        // Übersetzung schreiben
        $outputFile = $message->outputDir . '/' . $message->jobId . '_leichte_sprache.txt';
        file_put_contents($outputFile, $result->outputText);

        // Metadaten schreiben
        $metaFile = $message->outputDir . '/' . $message->jobId . '_meta.json';
        file_put_contents(
            $metaFile,
            json_encode($result->toMetadata($sourceFile), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );

        $this->logger->info('TranslationJobHandler: Job abgeschlossen', [
            'job_id' => $message->jobId,
            'output_file' => $outputFile,
            'meta_file' => $metaFile,
        ]);
    }
}
