<?php

namespace App\Services\Identification;

/**
 * Stand-in for the AI service, for tests and local development (IDENTIFICATION_DRIVER=fake).
 *
 * With nothing queued it answers `ok`, keeping Laravel's candidate order: the first is
 * "High", the second "Medium", the rest "Low". Tests queue exact responses or failures
 * and read back every payload that was sent.
 */
class FakeIdentificationClient implements IdentificationClient
{
    /** @var list<mixed|\Closure|IdentificationFailed> */
    private array $queue = [];

    /** @var list<array> payloads received, in order */
    public array $calls = [];

    /** Queue a response body (array), a closure fn(array $payload): mixed, or an IdentificationFailed. */
    public function push(mixed $response): static
    {
        $this->queue[] = $response;

        return $this;
    }

    public function identify(array $payload): mixed
    {
        $this->calls[] = $payload;
        $next = array_shift($this->queue);

        if ($next instanceof IdentificationFailed) {
            throw $next;
        }
        if ($next instanceof \Closure) {
            return $next($payload);
        }

        return $next ?? $this->rankInOrder($payload);
    }

    public function modelName(): ?string
    {
        return 'fake';
    }

    private function rankInOrder(array $payload): array
    {
        $labels = ['High', 'Medium'];

        return [
            'status' => $payload['candidates'] === [] ? 'no_match' : 'ok',
            'normalised_description' => $payload['description'] ?? null,
            'attributes' => [],
            'ranked' => array_map(
                fn (array $c, int $i) => ['id' => $c['id'], 'label' => $labels[$i] ?? 'Low', 'reason' => 'Fake client: kept search order'],
                $payload['candidates'], array_keys($payload['candidates'])
            ),
            'clarification_questions' => [],
            'manual_review' => false,
        ];
    }
}
