<?php

declare(strict_types=1);

namespace Ndrstmr\LsKi\LlmGateway;

/**
 * Zentrale Abstraktion für die LLM-Kommunikation.
 *
 * Implementierungen:
 *   - MockLlmGateway   → lokale Entwicklung ohne GPU
 *   - VllmGateway      → Produktion auf A100-VM via vLLM OpenAI-API
 *
 * Der aktive Provider wird über die Env-Variable LLM_PROVIDER gesteuert.
 */
interface LlmGatewayInterface
{
    /**
     * Sendet eine Chat-Completion-Anfrage an das LLM.
     *
     * @param array<array{role: string, content: string}> $messages
     * @param array<string, mixed>                        $options  z.B. temperature, max_tokens
     */
    public function complete(array $messages, array $options = []): LlmResponse;

    /**
     * Gibt den Identifier des aktuell aktiven Modells zurück.
     */
    public function getActiveModel(): string;


    /**
     * Gibt alle verfügbaren Modelle zurück.
     *
     * @return string[]
     */
    public function getAvailableModels(): array;
}
