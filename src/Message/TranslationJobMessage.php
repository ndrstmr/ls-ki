<?php

declare(strict_types=1);

namespace Ndrstmr\LsKi\Message;

/**
 * Symfony Messenger Message für asynchrone Übersetzungsjobs.
 * Wird vom Inbox-Worker dispatcht und vom TranslationJobHandler verarbeitet.
 */
final readonly class TranslationJobMessage
{
    public function __construct(
        public string $jobId,
        public string $inputFilePath,
    ) {}
}
