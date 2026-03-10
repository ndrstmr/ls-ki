<?php

declare(strict_types=1);

namespace App\Dto;

use App\Agent\TranslationResult;

/**
 * Response-DTO für POST /api/translate (synchron).
 */
final class TranslateResponse
{
    public function __construct(
        public readonly string $jobId,
        public readonly string $inputText,
        public readonly string $outputText,
        public readonly string $model,
        public readonly int $processingTimeMs,
        public readonly string $promptVersion,
        public readonly ?array $qualityCheck = null,
    ) {}

    public static function fromResult(TranslationResult $result): self
    {
        return new self(
            jobId: $result->jobId,
            inputText: $result->inputText,
            outputText: $result->outputText,
            model: $result->model,
            processingTimeMs: $result->processingTimeMs,
            promptVersion: $result->promptVersion,
            qualityCheck: $result->qualityCheck,
        );
    }

    public function toArray(): array
    {
        return [
            'job_id' => $this->jobId,
            'input_text' => $this->inputText,
            'output_text' => $this->outputText,
            'model' => $this->model,
            'processing_time_ms' => $this->processingTimeMs,
            'prompt_version' => $this->promptVersion,
            'quality_check' => $this->qualityCheck,
        ];
    }
}
