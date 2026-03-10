<?php

declare(strict_types=1);

namespace Ndrstmr\LsKi\Command;

use Ndrstmr\LsKi\LlmGateway\LlmGatewayInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * app:model:switch <model-name>
 *
 * Wechselt das aktive LLM-Modell im laufenden Gateway-State.
 * Für einen permanenten Wechsel auf der VM: switch-model.sh verwenden.
 *
 * Referenz: Anforderungsdokument Abschnitt 4.2.2 (CLI), Abschnitt 6 (Modellwechsel)
 */
#[AsCommand(
    name: 'app:model:switch',
    description: 'Wechselt das aktive LLM-Modell.',
)]
final class ModelSwitchCommand extends Command
{
    public function __construct(
        private readonly LlmGatewayInterface $llmGateway,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument(
            'model',
            InputArgument::REQUIRED,
            'Modell-ID (z.B. mistralai/Mistral-Small-3.1-24B-Instruct-2503)'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $modelId = $input->getArgument('model');

        $current = $this->llmGateway->getActiveModel();
        $io->writeln(sprintf('<comment>Aktuelles Modell:</comment> %s', $current));

        try {
            $this->llmGateway->switchModel($modelId);
        } catch (\InvalidArgumentException $e) {
            $io->error($e->getMessage());

            $io->writeln('<info>Verfügbare Modelle:</info>');
            foreach ($this->llmGateway->getAvailableModels() as $m) {
                $io->writeln('  - ' . $m);
            }

            return Command::FAILURE;
        }

        $io->success(sprintf('Modell gewechselt: %s → %s', $current, $modelId));

        return Command::SUCCESS;
    }
}
