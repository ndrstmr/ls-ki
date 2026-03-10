<?php

declare(strict_types=1);

namespace App\Command;

use App\Message\TranslationJobMessage;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Uuid;

/**
 * app:process-inbox
 *
 * Liest alle .txt-Dateien aus dem Inbox-Verzeichnis und
 * dispatcht für jede einen asynchronen TranslationJob.
 *
 * Referenz: Anforderungsdokument Abschnitt 4.2.2 (CLI), Datenfluss Abschnitt 4.3
 */
#[AsCommand(
    name: 'app:process-inbox',
    description: 'Dispatcht alle Textdateien aus dem Inbox-Verzeichnis als Übersetzungsjobs.',
)]
final class ProcessInboxCommand extends Command
{
    public function __construct(
        private readonly MessageBusInterface $messageBus,
        private readonly string $storageInbox,
        private readonly string $storageOutbox,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'dry-run',
            null,
            InputOption::VALUE_NONE,
            'Nur anzeigen, keine Jobs dispatchen'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = $input->getOption('dry-run');

        if (!is_dir($this->storageInbox)) {
            $io->warning(sprintf('Inbox-Verzeichnis existiert nicht: %s', $this->storageInbox));
            return Command::SUCCESS;
        }

        $files = glob($this->storageInbox . '/*.txt');

        if (empty($files)) {
            $io->info('Inbox ist leer.');
            return Command::SUCCESS;
        }

        $io->writeln(sprintf('<info>%d Datei(en) in Inbox gefunden.</info>', count($files)));

        if ($dryRun) {
            $io->writeln('<comment>[Dry-Run – keine Jobs werden dispatcht]</comment>');
        }

        $dispatched = 0;
        foreach ($files as $file) {
            $jobId = Uuid::v4()->toRfc4122();
            $filename = basename($file);

            if ($dryRun) {
                $io->writeln(sprintf('  → würde dispatchen: %s (Job-ID: %s)', $filename, $jobId));
                continue;
            }

            $this->messageBus->dispatch(new TranslationJobMessage(
                jobId: $jobId,
                inputFilePath: $file,
                outputDir: $this->storageOutbox,
            ));

            $io->writeln(sprintf('  ✓ dispatcht: %s (Job-ID: %s)', $filename, $jobId));
            $dispatched++;
        }

        if (!$dryRun) {
            $io->success(sprintf('%d Job(s) in Queue eingereiht.', $dispatched));
        }

        return Command::SUCCESS;
    }
}
