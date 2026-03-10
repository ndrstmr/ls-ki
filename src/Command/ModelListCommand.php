<?php

declare(strict_types=1);

namespace App\Command;

use App\LlmGateway\LlmGatewayInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * app:model:list
 *
 * Zeigt alle verfügbaren Modelle und das aktuell aktive Modell.
 *
 * Referenz: Anforderungsdokument Abschnitt 4.2.2 (CLI)
 */
#[AsCommand(
    name: 'app:model:list',
    description: 'Listet verfügbare LLM-Modelle auf.',
)]
final class ModelListCommand extends Command
{
    public function __construct(
        private readonly LlmGatewayInterface $llmGateway,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $active = $this->llmGateway->getActiveModel();
        $models = $this->llmGateway->getAvailableModels();

        $io->title('Verfügbare LLM-Modelle');

        $rows = array_map(fn(string $id) => [
            $id === $active ? '<info>✓ ' . $id . '</info>' : '  ' . $id,
            $id === $active ? '<info>aktiv</info>' : '',
        ], $models);

        $io->table(['Modell', 'Status'], $rows);
        $io->writeln(sprintf('<comment>Provider:</comment> %s', $_ENV['LLM_PROVIDER'] ?? getenv('LLM_PROVIDER') ?: 'mock'));

        return Command::SUCCESS;
    }
}
