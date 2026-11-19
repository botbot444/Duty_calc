<?php

namespace App\Services\Identification;

/**
 * Talks to the product identification service. Implementations return the decoded JSON
 * response as-is; AiResponseValidator decides what to trust.
 *
 * Request (plan, section 8):
 *   {input_type, description, language, image_ref, candidates: [{id, hs_code, description}],
 *    clarifications: [{question, answer}]}
 */
interface IdentificationClient
{
    /**
     * @return mixed the decoded response body
     *
     * @throws IdentificationFailed on timeout, connection error, non-2xx status or bad JSON
     */
    public function identify(array $payload): mixed;

    /** Model name to record on the analysis, if known. */
    public function modelName(): ?string;
}
