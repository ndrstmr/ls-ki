<?php

declare(strict_types=1);

namespace Ndrstmr\LsKi\Tool;

/**
 * Standard-Implementierung der ToolRegistry.
 * Tools werden per Symfony-DI-Tag automatisch registriert.
 */
final class ToolRegistry implements ToolRegistryInterface
{
    /** @var array<string, ToolInterface> */
    private array $tools = [];

    /** @param iterable<ToolInterface> $tools */
    public function __construct(iterable $tools = [])
    {
        foreach ($tools as $tool) {
            $this->register($tool);
        }
    }

    public function register(ToolInterface $tool): void
    {
        $this->tools[$tool->getName()] = $tool;
    }

    public function get(string $name): ToolInterface
    {
        if (!$this->has($name)) {
            throw new \InvalidArgumentException(sprintf('Tool "%s" nicht registriert.', $name));
        }

        return $this->tools[$name];
    }

    public function has(string $name): bool
    {
        return isset($this->tools[$name]);
    }

    public function all(): array
    {
        return array_values($this->tools);
    }
}
