<?php

declare(strict_types=1);

namespace App\Tool;

/**
 * QualityCheckTool – STUB für die CuP&Connect Live-Demo.
 *
 * Dieses Tool wird LIVE während der Präsentation durch den Copilot-Agenten
 * implementiert – als Demonstration agentischer Software-Entwicklung.
 *
 * Was der Agent implementieren wird:
 * - LLM-Aufruf mit quality-check.txt Prompt (config/prompts/v1.0/quality-check.txt)
 * - JSON-Antwort parsen: score, issues[], summary
 * - Rückgabe als strukturiertes Array
 *
 * Die ToolRegistry und der TranslationAgent sind bereits vorbereitet.
 * Dieses Tool muss nur den Tag "ls_ki.tool" erhalten (siehe services.yaml),
 * dann wird es automatisch registriert.
 *
 * @see docs/DEMO.md – Prompt und Ablauf für die Live-Demo
 */
final class QualityCheckTool implements ToolInterface
{
    public function getName(): string
    {
        return 'quality_check';
    }

    public function getDescription(): string
    {
        return 'Prüft einen Leichte-Sprache-Text gegen das Regelwerk und gibt Score + Verbesserungshinweise zurück.';
    }

    /**
     * @param array{text: string} $input
     * @return array{score: int, issues: array<mixed>, summary: string}
     */
    public function execute(array $input): mixed
    {
        // TODO: Wird live implementiert auf der CuP&Connect Demo
        // Erwarteter Input:  ['text' => '<zu prüfender Text>']
        // Erwarteter Output: ['score' => 0-100, 'issues' => [...], 'summary' => '...']
        throw new \LogicException('QualityCheckTool noch nicht implementiert – wird live auf der Demo fertiggestellt.');
    }
}
