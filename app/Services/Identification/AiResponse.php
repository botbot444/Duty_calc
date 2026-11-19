<?php

namespace App\Services\Identification;

/**
 * A response from the identification service that passed AiResponseValidator.
 * Every ID in $ranked was sent by Laravel and exists as an active catalogue item.
 */
final class AiResponse
{
    public const STATUSES = ['ok', 'no_match', 'needs_clarification', 'error'];

    public const LABELS = ['High', 'Medium', 'Low'];

    /**
     * @param  list<array{id: string, label: string, reason: ?string}>  $ranked
     * @param  list<string>  $clarificationQuestions
     * @param  list<string>  $droppedIds  IDs the service returned that Laravel did not accept
     */
    public function __construct(
        public readonly string $status,
        public readonly ?string $normalisedDescription,
        public readonly array $attributes,
        public readonly array $ranked,
        public readonly array $clarificationQuestions,
        public readonly bool $manualReview,
        public readonly array $droppedIds,
    ) {}
}
