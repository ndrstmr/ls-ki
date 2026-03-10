<?php

declare(strict_types=1);

namespace Ndrstmr\LsKi\Controller;

use Ndrstmr\LsKi\Agent\TranslationAgent;
use Ndrstmr\LsKi\Dto\TranslateRequest;
use Ndrstmr\LsKi\Dto\TranslateResponse;
use Ndrstmr\LsKi\Message\TranslationJobMessage;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * REST API für den Übersetzungsservice.
 *
 * Referenz: Anforderungsdokument Abschnitt 4.2.2 (REST API)
 */
#[Route('/api', format: 'json')]
final class TranslationController extends AbstractController
{
    public function __construct(
        private readonly TranslationAgent $translationAgent,
        private readonly MessageBusInterface $messageBus,
        private readonly ValidatorInterface $validator,
        private readonly string $storageInbox,
        private readonly string $storageOutbox,
    ) {}

    /**
     * POST /api/translate
     * Synchrone Übersetzung – wartet auf das LLM-Ergebnis.
     * Für Demo und direkte TYPO3-Integration.
     */
    #[Route('/translate', methods: ['POST'])]
    public function translate(Request $request): JsonResponse
    {
        $dto = $this->parseRequest($request);
        if ($dto instanceof JsonResponse) {
            return $dto;
        }

        $errors = $this->validator->validate($dto);
        if (count($errors) > 0) {
            return $this->validationErrorResponse($errors);
        }

        $jobId = Uuid::v4()->toRfc4122();

        try {
            $result = $this->translationAgent->translate($dto->text, $jobId);
        } catch (\Throwable $e) {
            return $this->json(['error' => 'Übersetzung fehlgeschlagen: ' . $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return $this->json(TranslateResponse::fromResult($result)->toArray(), Response::HTTP_OK);
    }

    /**
     * POST /api/jobs
     * Asynchroner Job – gibt sofort eine Job-ID zurück.
     * Der Worker verarbeitet den Job im Hintergrund (Inbox/Outbox-Pattern).
     */
    #[Route('/jobs', methods: ['POST'])]
    public function createJob(Request $request): JsonResponse
    {
        $dto = $this->parseRequest($request);
        if ($dto instanceof JsonResponse) {
            return $dto;
        }

        $errors = $this->validator->validate($dto);
        if (count($errors) > 0) {
            return $this->validationErrorResponse($errors);
        }

        $jobId = Uuid::v4()->toRfc4122();

        // Text in Inbox-Datei schreiben
        if (!is_dir($this->storageInbox)) {
            mkdir($this->storageInbox, 0755, true);
        }
        $inputFile = $this->storageInbox . '/' . $jobId . '_input.txt';
        file_put_contents($inputFile, $dto->text);

        $this->messageBus->dispatch(new TranslationJobMessage(
            jobId: $jobId,
            inputFilePath: $inputFile,
            outputDir: $this->storageOutbox,
        ));

        return $this->json([
            'job_id' => $jobId,
            'status' => 'queued',
            'links' => [
                'status' => '/api/jobs/' . $jobId,
            ],
        ], Response::HTTP_ACCEPTED);
    }

    /**
     * GET /api/jobs/{id}
     * Job-Status und Ergebnis abfragen.
     */
    #[Route('/jobs/{id}', methods: ['GET'])]
    public function getJob(string $id): JsonResponse
    {
        // Prüfe ob Ergebnis in Outbox vorhanden
        $metaFile = $this->storageOutbox . '/' . $id . '_meta.json';
        $outputFile = $this->storageOutbox . '/' . $id . '_leichte_sprache.txt';

        if (file_exists($metaFile) && file_exists($outputFile)) {
            $meta = json_decode(file_get_contents($metaFile), true);
            $output = file_get_contents($outputFile);

            return $this->json([
                'job_id' => $id,
                'status' => 'completed',
                'output_text' => $output,
                'metadata' => $meta,
            ]);
        }

        // Prüfe ob Input in Inbox vorhanden (= noch in Queue)
        $inputFile = $this->storageInbox . '/' . $id . '_input.txt';
        if (file_exists($inputFile)) {
            return $this->json([
                'job_id' => $id,
                'status' => 'processing',
            ]);
        }

        return $this->json(['error' => 'Job nicht gefunden: ' . $id], Response::HTTP_NOT_FOUND);
    }

    private function parseRequest(Request $request): TranslateRequest|JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
            return $this->json(['error' => 'Ungültiges JSON.'], Response::HTTP_BAD_REQUEST);
        }

        return new TranslateRequest(
            text: $data['text'] ?? '',
            mode: $data['mode'] ?? 'leichte_sprache',
            qualityCheck: (bool) ($data['quality_check'] ?? false),
        );
    }

    private function validationErrorResponse(iterable $errors): JsonResponse
    {
        $messages = [];
        foreach ($errors as $error) {
            $messages[] = $error->getMessage();
        }

        return $this->json(['errors' => $messages], Response::HTTP_UNPROCESSABLE_ENTITY);
    }
}
