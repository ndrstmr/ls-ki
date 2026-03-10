<?php

declare(strict_types=1);

namespace App\Command;

use App\Agent\TranslationAgent;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Uid\Uuid;

/**
 * app:translate <datei>
 *
 * Übersetzt eine einzelne Textdatei in Leichte Sprache.
 * Gibt Ergebnis auf stdout aus oder schreibt in Ausgabedatei.
 *
 * Referenz: Anforderungsdokument Abschnitt 4.2.2 (CLI)
 */
#[AsCommand(
    name: 'app:translate',
    description: 'Übersetzt eine Textdatei in Leichte Sprache.',
)]
final class TranslateCommand extends Command
{
    public function __construct(
        private readonly TranslationAgent $translationAgent,
        private readonly string $storageOutbox,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('file', InputArgument::REQUIRED, 'Pfad zur Eingabedatei (.txt)')
            ->addOption('output', 'o', InputOption::VALUE_OPTIONAL, 'Ausgabedatei (Standard: stdout)')
            ->addOption('save-meta', null, InputOption::VALUE_NONE, 'Metadaten-JSON in Outbox speichern');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $file = $input->getArgument('file');

        if (!file_exists($file)) {
            $io->error(sprintf('Datei nicht gefunden: %s', $file));
            return Command::FAILURE;
        }

        $inputText = file_get_contents($file);
        if (empty(trim($inputText))) {
            $io->error('Datei ist leer.');
            return Command::FAILURE;
        }

        $jobId = Uuid::v4()->toRfc4122();

        $io->writeln(sprintf('<info>Übersetze:</info> %s', basename($file)));
        $io->writeln(sprintf('<comment>Job-ID:</comment>  %s', $jobId));

        try {
            $result = $this->translationAgent->translate($inputText, $jobId);
        } catch (\Throwable $e) {
            $io->error('Übersetzung fehlgeschlagen: ' . $e->getMessage());
            return Command::FAILURE;
        }

        $io->writeln(sprintf('<comment>Modell:</comment>  %s', $result->model));
        $io->writeln(sprintf('<comment>Dauer:</comment>   %d ms', $result->processingTimeMs));
        $io->newLine();

        // Ausgabe
        $outputFile = $input->getOption('output');
        if ($outputFile) {
            file_put_contents($outputFile, $result->outputText);
            $io->success(sprintf('Übersetzung gespeichert: %s', $outputFile));
        } else {
            $io->section('Leichte Sprache:');
            $output->writeln($result->outputText);
        }

        // Optionale Metadaten
        if ($input->getOption('save-meta')) {
            if (!is_dir($this->storageOutbox)) {
                mkdir($this->storageOutbox, 0755, true);
            }
            $metaFile = $this->storageOutbox . '/' . $jobId . '_meta.json';
            file_put_contents(
                $metaFile,
                json_encode($result->toMetadata(basename($file)), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
            );
            $io->writeln(sprintf('<comment>Metadaten:</comment> %s', $metaFile));
        }

        return Command::SUCCESS;
    }
}
