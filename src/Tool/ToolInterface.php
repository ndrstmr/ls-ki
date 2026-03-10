<?php

declare(strict_types=1);

namespace Ndrstmr\LsKi\Tool;

/**
 * Basis-Interface für alle Agent-Tools.
 *
 * Jedes Tool hat einen eindeutigen Namen, eine Beschreibung
 * und eine execute()-Methode.
 */
interface ToolInterface
{
    public function getName(): string;

    public function getDescription(): string;

    /** @param array<string, mixed> $input */
    public function execute(array $input): mixed;
}
