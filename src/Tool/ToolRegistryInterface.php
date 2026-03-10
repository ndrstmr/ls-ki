<?php

declare(strict_types=1);

namespace Ndrstmr\LsKi\Tool;

/**
 * Registry für verfügbare Tools des TranslationAgent.
 *
 * Ermöglicht das Registrieren neuer Tools zur Laufzeit –
 * das ist das Live-Feature für die CuP&Connect-Demo:
 * Das QualityCheckTool wird live in der Präsentation hinzugefügt.
 *
 * Referenz: Anforderungsdokument Abschnitt 4.2.2
 */
interface ToolRegistryInterface
{
    public function register(ToolInterface $tool): void;

    public function get(string $name): ToolInterface;

    public function has(string $name): bool;

    /** @return ToolInterface[] */
    public function all(): array;
}
