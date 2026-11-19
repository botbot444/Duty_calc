<?php

namespace App\Services\Identification;

use App\Models\CatalogueItem;

/**
 * Decides what to trust in a response from the identification service.
 *
 * - The body must be an object with a known `status`; anything else is InvalidAiResponse.
 * - `ranked` IDs are kept only if Laravel sent them as candidates AND they exist as active
 *   catalogue items (the AI returns catalogue IDs only, never HS codes or rates).
 *   Unknown IDs are dropped and reported, duplicates keep their first position.
 * - An unknown confidence label becomes "Low": never more confident than the AI said.
 * - `ok` with nothing left after dropping becomes `no_match`; `needs_clarification`
 *   with no questions becomes `ok` (or `no_match` if nothing is ranked).
 */
class AiResponseValidator
{
    private const MAX_QUESTIONS = 3;

    private const MAX_TEXT = 500;

    /** @param list<string> $candidateIds the IDs Laravel sent */
    public function validate(mixed $body, array $candidateIds): AiResponse
    {
        if (! is_array($body) || array_is_list($body)) {
            throw new InvalidAiResponse('response is not a JSON object');
        }

        $status = $body['status'] ?? null;
        if (! is_string($status) || ! in_array($status, AiResponse::STATUSES, true)) {
            throw new InvalidAiResponse('unknown status: '.json_encode($status));
        }

        $rankedIn = $body['ranked'] ?? [];
        if (! is_array($rankedIn) || ! array_is_list($rankedIn)) {
            throw new InvalidAiResponse('ranked must be a list');
        }

        $returnedIds = [];
        foreach ($rankedIn as $entry) {
            if (is_array($entry) && is_string($entry['id'] ?? null)) {
                $returnedIds[] = $entry['id'];
            }
        }
        $known = CatalogueItem::active()
            ->whereIn('id', array_values(array_intersect($returnedIds, $candidateIds)))
            ->pluck('id')
            ->all();

        $ranked = [];
        $dropped = [];
        foreach ($rankedIn as $entry) {
            $id = is_array($entry) ? ($entry['id'] ?? null) : null;
            if (! is_string($id) || ! in_array($id, $known, true)) {
                $dropped[] = is_scalar($id) ? (string) $id : json_encode($entry);

                continue;
            }
            if (isset($ranked[$id])) {
                continue;
            }
            $label = $entry['label'] ?? null;
            $ranked[$id] = [
                'id' => $id,
                'label' => in_array($label, AiResponse::LABELS, true) ? $label : 'Low',
                'reason' => $this->text($entry['reason'] ?? null),
            ];
        }
        $ranked = array_values($ranked);

        $questions = [];
        foreach ((array) ($body['clarification_questions'] ?? []) as $q) {
            if (is_string($q) && trim($q) !== '' && count($questions) < self::MAX_QUESTIONS) {
                $questions[] = mb_substr(trim($q), 0, 300);
            }
        }

        if ($status === 'needs_clarification' && $questions === []) {
            $status = 'ok';
        }
        if ($status === 'ok' && $ranked === []) {
            $status = 'no_match';
        }

        $attributes = [];
        foreach ((array) ($body['attributes'] ?? []) as $key => $value) {
            if (is_string($key) && (is_scalar($value) || $value === null)) {
                $attributes[mb_substr($key, 0, 50)] = is_string($value) ? mb_substr($value, 0, 200) : $value;
            }
        }

        return new AiResponse(
            status: $status,
            normalisedDescription: $this->text($body['normalised_description'] ?? null),
            attributes: $attributes,
            ranked: $ranked,
            clarificationQuestions: $status === 'needs_clarification' ? $questions : [],
            manualReview: ($body['manual_review'] ?? false) === true,
            droppedIds: $dropped,
        );
    }

    private function text(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? mb_substr(trim($value), 0, self::MAX_TEXT) : null;
    }
}
