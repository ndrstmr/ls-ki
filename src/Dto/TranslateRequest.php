<?php

declare(strict_types=1);

namespace Ndrstmr\LsKi\Dto;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Request-DTO für POST /api/translate und POST /api/jobs.
 */
final class TranslateRequest
{
    public function __construct(
        #[Assert\NotBlank(message: 'Der Text darf nicht leer sein.')]
        #[Assert\Length(
            min: 10,
            max: 50000,
            minMessage: 'Der Text muss mindestens {{ limit }} Zeichen lang sein.',
            maxMessage: 'Der Text darf maximal {{ limit }} Zeichen lang sein.',
        )]
        public readonly string $text = '',

        #[Assert\Choice(
            choices: ['leichte_sprache'],
            message: 'Ungültiger Modus. Erlaubt: leichte_sprache',
        )]
        public readonly string $mode = 'leichte_sprache',

        public readonly bool $qualityCheck = false,
    ) {}
}
