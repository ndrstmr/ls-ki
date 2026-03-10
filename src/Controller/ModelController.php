<?php

declare(strict_types=1);

namespace Ndrstmr\LsKi\Controller;

use Ndrstmr\LsKi\LlmGateway\LlmGatewayInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * API-Endpunkte für Modell-Verwaltung.
 *
 * GET  /api/models              – verfügbare Modelle auflisten
 * GET  /api/models/active       – aktives Modell anzeigen
 * PUT  /api/config/model        – aktives Modell wechseln (Demo-Feature!)
 *
 * Der Modellwechsel via API ändert den laufenden Gateway-State.
 * Für einen persistenten Wechsel: switch-model.sh + Docker-Override.
 *
 * Referenz: Anforderungsdokument Abschnitt 4.2.2, Abschnitt 6
 */
#[Route('/api', format: 'json')]
final class ModelController extends AbstractController
{
    public function __construct(
        private readonly LlmGatewayInterface $llmGateway,
    ) {}

    /**
     * GET /api/models
     * Listet alle verfügbaren Modelle auf.
     */
    #[Route('/models', methods: ['GET'])]
    public function listModels(): JsonResponse
    {
        $models = $this->llmGateway->getAvailableModels();
        $active = $this->llmGateway->getActiveModel();

        return $this->json([
            'models' => array_map(fn(string $id) => [
                'id' => $id,
                'active' => $id === $active,
            ], $models),
            'active_model' => $active,
        ]);
    }

    /**
     * GET /api/models/active
     * Gibt das aktive Modell zurück.
     */
    #[Route('/models/active', methods: ['GET'])]
    public function activeModel(): JsonResponse
    {
        return $this->json([
            'active_model' => $this->llmGateway->getActiveModel(),
        ]);
    }

    /**
     * PUT /api/config/model
     * Wechselt das aktive Modell zur Laufzeit.
     *
     * Body: { "model_id": "mistralai/Mistral-Small-3.1-24B-Instruct-2503" }
     *
     * Demo-Feature: zeigt live den Modellwechsel ohne Neustart.
     * Hinweis: Ändert nur den Gateway-State dieser PHP-FPM-Instanz.
     * Für permanenten Wechsel: switch-model.sh verwenden.
     */
    #[Route('/config/model', methods: ['PUT'])]
    public function switchModel(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (json_last_error() !== JSON_ERROR_NONE || empty($data['model_id'])) {
            return $this->json(['error' => 'Pflichtfeld: model_id'], Response::HTTP_BAD_REQUEST);
        }

        $modelId = (string) $data['model_id'];

        try {
            $this->llmGateway->switchModel($modelId);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $this->json([
            'message' => 'Modell gewechselt.',
            'active_model' => $this->llmGateway->getActiveModel(),
        ]);
    }
}
