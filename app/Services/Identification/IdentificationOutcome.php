<?php

namespace App\Services\Identification;

/**
 * What /api/identify/* returns. `results` use the same item fields as catalogue search
 * (plus the AI's label and reason), so the suggestions screen treats both routes alike.
 * The trader still confirms one item through POST /api/selection.
 */
final class IdentificationOutcome
{
    /**
     * @param  list<array<string, mixed>>  $results
     * @param  list<string>  $clarificationQuestions
     */
    public function __construct(
        public readonly int $analysisId,
        public readonly string $inputType,
        public readonly string $status,   // ok | no_match | needs_clarification | fallback | error
        public readonly bool $aiUsed,
        public readonly array $results,
        public readonly ?string $normalisedDescription = null,
        public readonly array $clarificationQuestions = [],
        public readonly int $round = 0,
        public readonly int $maxRounds = 2,
        public readonly bool $manualReview = false,
        public readonly ?string $message = null,
    ) {}

    public function toArray(): array
    {
        return [
            'analysis_id' => $this->analysisId,
            'input_type' => $this->inputType,
            'status' => $this->status,
            'ai_used' => $this->aiUsed,
            'normalised_description' => $this->normalisedDescription,
            'clarification_questions' => $this->clarificationQuestions,
            'round' => $this->round,
            'max_rounds' => $this->maxRounds,
            'manual_review' => $this->manualReview,
            'message' => $this->message,
            'count' => count($this->results),
            'results' => $this->results,
        ];
    }
}
