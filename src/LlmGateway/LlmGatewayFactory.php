<?php

declare(strict_types=1);

namespace Ndrstmr\LsKi\LlmGateway;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Factory für den LlmGateway.
 * Entscheidet anhand der Env-Variable LLM_PROVIDER welche Implementierung aktiv ist.
 *
 * LLM_PROVIDER=mock  → MockLlmGateway (lokale Entwicklung)
 * LLM_PROVIDER=vllm  → VllmGateway (Produktion auf A100-VM)
 */
final class LlmGatewayFactory
{
    public static function create(
        string $provider,
        string $activeModel,
        string $apiUrl,
        string $apiKey,
        HttpClientInterface $httpClient,
        LoggerInterface $logger,
    ): LlmGatewayInterface {
        return match ($provider) {
            'vllm' => new VllmGateway($httpClient, $logger, $apiUrl, $apiKey, $activeModel),
            'mock' => new MockLlmGateway($logger, $activeModel),
            default => throw new \InvalidArgumentException(
                sprintf('Unbekannter LLM_PROVIDER: "%s". Erlaubt: mock, vllm', $provider)
            ),
        };
    }
}
