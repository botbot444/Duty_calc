<?php

namespace App\Services\Identification;

use App\Enums\Language;
use App\Models\AiProductAnalysis;
use App\Models\CatalogueItem;
use App\Services\Catalogue\CatalogueSearchService;
use Illuminate\Contracts\Session\Session;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * Text and photo identification. The AI only re-ranks catalogue items that Laravel picked
 * as candidates; AiResponseValidator drops anything else. Neither flow selects an item:
 * both end at the same POST /api/selection step, then the same EstimateService.
 *
 * If the AI fails (timeout, bad JSON, wrong shape, status `error`), text identification
 * falls back to plain catalogue search, and photo identification asks the trader to type
 * the product name. The text route therefore never depends on the AI being up.
 */
class ProductIdentificationService
{
    private const SESSION_KEY = 'dutycalc.analyses';

    private const UPLOAD_DIR = 'identification-uploads';

    public const PHOTO_FAILED = "We couldn't identify this photo. Try typing the product name.";

    public const AI_UNAVAILABLE = 'Smart matching is not available right now, so these are plain search results.';

    public function __construct(
        private readonly IdentificationClient $client,
        private readonly AiResponseValidator $validator,
        private readonly CatalogueSearchService $search,
        private readonly Session $session,
    ) {}

    public function identifyText(string $description, Language $language): IdentificationOutcome
    {
        $searchResults = $this->search->search($description, $language);
        $candidates = $searchResults !== []
            ? $this->itemsInOrder(array_column($searchResults, 'id'))
            : $this->wholeCatalogueIfSmall();

        $analysis = $this->newAnalysis('text', $language, $description, $candidates);

        if ($candidates->isEmpty()) {
            return $this->finish($analysis, 'no_match', aiUsed: false, results: []);
        }

        return $this->ask(
            $analysis,
            $this->payload('text', $language, $description, $candidates, [], null),
            fallbackResults: array_map(fn ($r) => $this->row($r['id'], null, null, $candidates), $searchResults),
            fallbackMessage: null,
        );
    }

    public function identifyImage(UploadedFile $photo, ?string $description, Language $language): IdentificationOutcome
    {
        // Private disk, random file name; deleted in `finally` whatever happens.
        $path = Storage::disk('local')->putFile(self::UPLOAD_DIR, $photo);

        try {
            $candidates = $this->wholeCatalogueIfSmall();
            if ($candidates->isEmpty() && $description !== null) {
                $candidates = $this->itemsInOrder(array_column($this->search->search($description, $language), 'id'));
            }

            $analysis = $this->newAnalysis('image', $language, $description, $candidates);

            if ($candidates->isEmpty()) {
                return $this->finish($analysis, 'no_match', aiUsed: false, results: [], message: self::PHOTO_FAILED);
            }

            $imageRef = 'data:'.$photo->getMimeType().';base64,'.base64_encode(Storage::disk('local')->get($path));

            return $this->ask(
                $analysis,
                $this->payload('image', $language, $description, $candidates, [], $imageRef),
                fallbackResults: [],
                fallbackMessage: self::PHOTO_FAILED,
            );
        } finally {
            Storage::disk('local')->delete($path);
        }
    }

    /**
     * Send the trader's answers back for re-ranking, with the same candidates and the earlier
     * description. At most `max_clarification_rounds` rounds; after that the current ranking
     * is returned without calling the AI again.
     *
     * @param  list<array{question: string, answer: string}>  $answers
     */
    public function clarify(int $analysisId, array $answers): IdentificationOutcome
    {
        $analysis = $this->analysisFromSession($analysisId);
        $candidates = $this->itemsInOrder(array_column($analysis->candidates_sent, 'id'));
        $current = array_map(fn ($r) => $this->row($r['id'], $r['label'], $r['reason'], $candidates), $analysis->ranked);

        if ($analysis->status !== 'needs_clarification' || $analysis->rounds >= $this->maxRounds()) {
            $status = $analysis->status === 'needs_clarification' ? ($current === [] ? 'no_match' : 'ok') : $analysis->status;

            return $this->finish($analysis, $status, aiUsed: false, results: $current);
        }

        $clarifications = [...$analysis->clarifications, ...array_map(
            fn (array $a) => ['question' => $a['question'], 'answer' => $a['answer'], 'round' => $analysis->rounds + 1],
            $answers
        )];
        $analysis->fill(['clarifications' => $clarifications, 'rounds' => $analysis->rounds + 1]);

        return $this->ask(
            $analysis,
            $this->payload(
                $analysis->input_type,
                Language::from($analysis->language),
                $analysis->description ?? $analysis->normalised_description,
                $candidates,
                array_map(fn ($c) => ['question' => $c['question'], 'answer' => $c['answer']], $clarifications),
                null,   // the photo was deleted after the first round
            ),
            fallbackResults: $current,
            fallbackMessage: null,
        );
    }

    /** Record which item the trader finally confirmed against the analysis that suggested it. */
    public function recordConfirmation(int $analysisId, CatalogueItem $item): void
    {
        $this->analysisFromSession($analysisId)->update(['confirmed_catalogue_item_id' => $item->id]);
    }

    public function belongsToSession(int $analysisId): bool
    {
        return in_array($analysisId, $this->session->get(self::SESSION_KEY, []), true);
    }

    // ---------------------------------------------------------------------

    private function ask(AiProductAnalysis $analysis, array $payload, array $fallbackResults, ?string $fallbackMessage): IdentificationOutcome
    {
        $candidates = $this->itemsInOrder(array_column($payload['candidates'], 'id'));

        try {
            $response = $this->validator->validate($this->client->identify($payload), $candidates->keys()->all());
        } catch (IdentificationFailed $e) {
            return $this->fallBack($analysis, $e->reason, $fallbackResults, $fallbackMessage);
        } catch (InvalidAiResponse $e) {
            return $this->fallBack($analysis, 'invalid_response: '.mb_substr($e->getMessage(), 0, 70), $fallbackResults, $fallbackMessage);
        }

        if ($response->status === 'error') {
            return $this->fallBack($analysis, 'ai_error', $fallbackResults, $fallbackMessage);
        }

        $analysis->fill([
            'normalised_description' => $response->normalisedDescription,
            'product_attributes' => $response->attributes,
            'ranked' => $response->ranked,
            'dropped_ids' => [...$analysis->dropped_ids ?? [], ...$response->droppedIds],
            'manual_review' => $response->manualReview,
            'failure_reason' => null,
        ]);

        $results = array_map(fn ($r) => $this->row($r['id'], $r['label'], $r['reason'], $candidates), $response->ranked);

        // Out of clarification rounds: return the current ranking instead of another question.
        $status = $response->status;
        $questions = $response->clarificationQuestions;
        if ($status === 'needs_clarification' && $analysis->rounds >= $this->maxRounds()) {
            $status = $results === [] ? 'no_match' : 'ok';
            $questions = [];
        }

        return $this->finish(
            $analysis, $status, aiUsed: true, results: $results,
            questions: $questions,
            message: $status === 'no_match' && $analysis->input_type === 'image' ? self::PHOTO_FAILED : null,
        );
    }

    private function fallBack(AiProductAnalysis $analysis, string $reason, array $results, ?string $message): IdentificationOutcome
    {
        $analysis->failure_reason = $reason;

        return $this->finish(
            $analysis,
            $analysis->input_type === 'image' ? 'error' : 'fallback',
            aiUsed: false,
            results: $results,
            message: $message ?? ($results === [] ? null : self::AI_UNAVAILABLE),
        );
    }

    private function finish(AiProductAnalysis $analysis, string $status, bool $aiUsed, array $results, array $questions = [], ?string $message = null): IdentificationOutcome
    {
        $analysis->status = $status === 'fallback' || $status === 'error' ? 'fallback' : $status;
        $analysis->save();

        $ids = $this->session->get(self::SESSION_KEY, []);
        if (! in_array($analysis->id, $ids, true)) {
            $this->session->put(self::SESSION_KEY, array_slice([...$ids, $analysis->id], -20));
        }

        return new IdentificationOutcome(
            analysisId: $analysis->id,
            inputType: $analysis->input_type,
            status: $status,
            aiUsed: $aiUsed,
            results: $results,
            normalisedDescription: $analysis->normalised_description,
            clarificationQuestions: $questions,
            round: $analysis->rounds,
            maxRounds: $this->maxRounds(),
            manualReview: $analysis->manual_review,
            message: $message,
        );
    }

    private function newAnalysis(string $type, Language $language, ?string $description, Collection $candidates): AiProductAnalysis
    {
        return new AiProductAnalysis([
            'input_type' => $type,
            'language' => $language->value,
            'description' => $description,
            'product_attributes' => [],
            'candidates_sent' => $candidates->map(fn (CatalogueItem $i) => $this->candidate($i))->values()->all(),
            'ranked' => [],
            'clarifications' => [],
            'dropped_ids' => [],
            'rounds' => 0,
            'manual_review' => false,
            'model_name' => $this->client->modelName(),
        ]);
    }

    private function payload(string $type, Language $language, ?string $description, Collection $candidates, array $clarifications, ?string $imageRef): array
    {
        return [
            'input_type' => $type,
            'description' => $description,
            'language' => $language->value,
            'image_ref' => $imageRef,
            'candidates' => $candidates->map(fn (CatalogueItem $i) => $this->candidate($i))->values()->all(),
            'clarifications' => $clarifications,
        ];
    }

    private function candidate(CatalogueItem $item): array
    {
        return [
            'id' => $item->id,
            'hs_code' => $item->tariffLine->hs_code,
            'description' => $item->display_name.': '.$item->tariffLine->official_description,
        ];
    }

    /** Same item fields as catalogue search, plus the AI's label and reason. */
    private function row(string $id, ?string $label, ?string $reason, Collection $items): array
    {
        $item = $items[$id] ?? CatalogueItem::with('tariffLine')->findOrFail($id);

        return [
            'id' => $item->id,
            'name' => $item->display_name,
            'hs_code' => $item->tariffLine->hs_code,
            'official_description' => $item->tariffLine->official_description,
            'stat_unit' => $item->tariffLine->stat_unit->value,
            'label' => $label,
            'reason' => $reason,
        ];
    }

    /** @return Collection<string, CatalogueItem> keyed by ID, in the given order, usable items only */
    private function itemsInOrder(array $ids): Collection
    {
        $items = $this->usableItems()->whereIn('id', $ids)->get()->keyBy('id');

        return collect($ids)->filter(fn ($id) => $items->has($id))->mapWithKeys(fn ($id) => [$id => $items[$id]]);
    }

    /** With a small catalogue (20–30 goods) the whole catalogue is the candidate list. */
    private function wholeCatalogueIfSmall(): Collection
    {
        $max = (int) config('services.identification.max_candidates');
        if ($this->usableItems()->count() > $max) {
            return collect();
        }

        return $this->usableItems()->orderBy('id')->get()->keyBy('id');
    }

    private function usableItems()
    {
        return CatalogueItem::active()->whereHas('tariffLine', fn ($q) => $q->inForce())->with('tariffLine');
    }

    private function analysisFromSession(int $analysisId): AiProductAnalysis
    {
        if (! $this->belongsToSession($analysisId)) {
            throw (new ModelNotFoundException)->setModel(AiProductAnalysis::class, [$analysisId]);
        }

        return AiProductAnalysis::findOrFail($analysisId);
    }

    private function maxRounds(): int
    {
        return (int) config('services.identification.max_clarification_rounds');
    }
}
