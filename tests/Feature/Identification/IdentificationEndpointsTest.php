<?php

namespace Tests\Feature\Identification;

use App\Models\AiProductAnalysis;
use App\Models\ProductAlias;
use App\Services\Identification\FakeIdentificationClient;
use App\Services\Identification\IdentificationClient;
use App\Services\Identification\IdentificationFailed;
use App\Services\Identification\ProductIdentificationService;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\FullTextDatabase;
use Tests\Support\TariffFixtures;
use Tests\TestCase;

/**
 * /api/identify/* with FakeIdentificationClient: every status, every failure, and photo uploads.
 * FullTextDatabase because text identification starts from catalogue search.
 */
class IdentificationEndpointsTest extends TestCase
{
    use FullTextDatabase;
    use TariffFixtures;

    /** A real 1x1 PNG. GD is not installed, so images are written as bytes. */
    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

    private const USABLE_ITEMS = 7;   // active fixture items on a valid line

    private FakeIdentificationClient $ai;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-11-20 10:00', 'Africa/Lusaka'));
        Storage::fake('local');
        $this->seedTariffFixtures();
        ProductAlias::create(['catalogue_item_id' => 'CAT-0004', 'alias' => 'sukari', 'language' => 'sw']);
        ProductAlias::create(['catalogue_item_id' => 'CAT-0001', 'alias' => 'shati', 'language' => 'sw']);
        ProductAlias::create(['catalogue_item_id' => 'CAT-0002', 'alias' => 'shati ya ndani', 'language' => 'sw']);

        $this->ai = new FakeIdentificationClient;
        $this->app->instance(IdentificationClient::class, $this->ai);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function identifyText(string $description = 'sukari', string $lang = 'sw')
    {
        return $this->postJson('/api/identify/text', ['description' => $description, 'lang' => $lang]);
    }

    private function photo(string $name = 'photo.png', ?string $bytes = null): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, $bytes ?? base64_decode(self::PNG));
    }

    private function identifyImage(UploadedFile $file, array $extra = [])
    {
        return $this->post('/api/identify/image', ['photo' => $file] + $extra, ['Accept' => 'application/json']);
    }

    private function uploadsLeft(): array
    {
        return Storage::disk('local')->allFiles('identification-uploads');
    }

    private function ok(array $ranked, array $extra = []): array
    {
        return ['status' => 'ok', 'normalised_description' => 'white sugar', 'attributes' => ['form' => 'granulated'],
            'ranked' => $ranked, 'clarification_questions' => [], 'manual_review' => false] + $extra;
    }

    // --- text: each status ------------------------------------------------

    public function test_ok_returns_the_ai_ranking_in_the_search_result_shape(): void
    {
        $this->ai->push($this->ok([['id' => 'CAT-0004', 'label' => 'High', 'reason' => 'sukari is sugar']]));

        $response = $this->identifyText()->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('ai_used', true)
            ->assertJsonPath('results.0.id', 'CAT-0004')
            ->assertJsonPath('results.0.hs_code', '17019900')
            ->assertJsonPath('results.0.label', 'High')
            ->assertJsonPath('results.0.reason', 'sukari is sugar')
            ->assertJsonPath('normalised_description', 'white sugar');

        // Laravel sent the search candidates, in the agreed shape.
        $sent = $this->ai->calls[0];
        $this->assertSame('text', $sent['input_type']);
        $this->assertSame('sw', $sent['language']);
        $this->assertSame([['id' => 'CAT-0004', 'hs_code' => '17019900', 'description' => 'Sugar: Sugar (test fixture)']], $sent['candidates']);

        $analysis = AiProductAnalysis::findOrFail($response->json('analysis_id'));
        $this->assertSame('ok', $analysis->status);
        $this->assertSame('fake', $analysis->model_name);
        $this->assertSame(['form' => 'granulated'], $analysis->product_attributes);
        $this->assertNull($analysis->confirmed_catalogue_item_id);   // nothing is auto-selected
    }

    public function test_the_ai_can_reorder_the_search_candidates(): void
    {
        $this->ai->push($this->ok([['id' => 'CAT-0002', 'label' => 'High'], ['id' => 'CAT-0001', 'label' => 'Medium']]));

        $this->identifyText('shati')->assertJsonPath('results.*.id', ['CAT-0002', 'CAT-0001']);
        $this->assertEqualsCanonicalizing(['CAT-0001', 'CAT-0002'], array_column($this->ai->calls[0]['candidates'], 'id'));
    }

    public function test_unknown_and_unsent_ids_are_dropped(): void
    {
        $this->ai->push($this->ok([
            ['id' => 'CAT-9999', 'label' => 'High'], ['id' => 'CAT-0006', 'label' => 'High'], ['id' => 'CAT-0004', 'label' => 'Low'],
        ]));

        $response = $this->identifyText()->assertJsonPath('results.*.id', ['CAT-0004']);

        $this->assertSame(['CAT-9999', 'CAT-0006'], AiProductAnalysis::find($response->json('analysis_id'))->dropped_ids);
    }

    public function test_only_unknown_ids_means_no_match(): void
    {
        $this->ai->push($this->ok([['id' => 'CAT-9999', 'label' => 'High']]));

        $this->identifyText()->assertJsonPath('status', 'no_match')->assertJsonPath('results', []);
    }

    public function test_no_match_from_the_ai_is_returned(): void
    {
        $this->ai->push(['status' => 'no_match', 'ranked' => []]);

        $this->identifyText()->assertJsonPath('status', 'no_match')->assertJsonPath('ai_used', true)->assertJsonPath('results', []);
    }

    public function test_text_with_no_search_hits_sends_the_whole_small_catalogue(): void
    {
        $this->identifyText('something odd', 'en')->assertOk();

        $this->assertCount(self::USABLE_ITEMS, $this->ai->calls[0]['candidates']);
        $this->assertNotContains('CAT-0099', array_column($this->ai->calls[0]['candidates'], 'id'));
    }

    public function test_a_large_catalogue_is_not_sent_when_search_finds_nothing(): void
    {
        config(['services.identification.max_candidates' => 3]);

        $this->identifyText('something odd', 'en')->assertJsonPath('status', 'no_match');
        $this->assertSame([], $this->ai->calls);
    }

    // --- text: failures fall back to plain search ----------------------------

    public static function failures(): array
    {
        return [
            'status error' => [['status' => 'error'], 'ai_error'],
            'bad JSON' => [new IdentificationFailed('bad_json'), 'bad_json'],
            'timeout' => [new IdentificationFailed('timeout'), 'timeout'],
            'service down' => [new IdentificationFailed('http_503'), 'http_503'],
            'unknown status' => [['status' => 'maybe'], 'invalid_response: unknown status: "maybe"'],
            'not an object' => [['CAT-0004'], 'invalid_response: response is not a JSON object'],
        ];
    }

    #[DataProvider('failures')]
    public function test_any_ai_failure_falls_back_to_plain_search(mixed $failure, string $reason): void
    {
        $this->ai->push($failure);

        $response = $this->identifyText()->assertOk()
            ->assertJsonPath('status', 'fallback')
            ->assertJsonPath('ai_used', false)
            ->assertJsonPath('results.*.id', ['CAT-0004'])
            ->assertJsonPath('results.0.label', null)
            ->assertJsonPath('message', ProductIdentificationService::AI_UNAVAILABLE);

        $analysis = AiProductAnalysis::findOrFail($response->json('analysis_id'));
        $this->assertSame('fallback', $analysis->status);
        $this->assertSame($reason, $analysis->failure_reason);
    }

    public function test_plain_search_still_works_while_the_ai_is_down(): void
    {
        $this->ai->push(new IdentificationFailed('timeout'));
        $this->identifyText();

        $this->getJson('/api/catalogue/search?q=sukari')->assertOk()->assertJsonPath('results.0.id', 'CAT-0004');
    }

    // --- clarification loop -----------------------------------------------

    public function test_a_clarification_round_sends_the_answers_with_the_same_candidates(): void
    {
        $this->ai->push(['status' => 'needs_clarification', 'normalised_description' => 'cotton shirt',
            'ranked' => [['id' => 'CAT-0001', 'label' => 'Medium'], ['id' => 'CAT-0002', 'label' => 'Medium']],
            'clarification_questions' => ['Does it have sleeves?']]);
        $this->ai->push($this->ok([['id' => 'CAT-0002', 'label' => 'High']]));

        $first = $this->identifyText('shati')
            ->assertJsonPath('status', 'needs_clarification')
            ->assertJsonPath('clarification_questions', ['Does it have sleeves?'])
            ->assertJsonPath('round', 0)
            ->assertJsonPath('max_rounds', 2);

        $second = $this->postJson('/api/identify/clarify', [
            'analysis_id' => $first->json('analysis_id'),
            'answers' => [['question' => 'Does it have sleeves?', 'answer' => 'Hapana']],
        ])->assertOk()->assertJsonPath('status', 'ok')->assertJsonPath('round', 1)->assertJsonPath('results.*.id', ['CAT-0002']);

        $this->assertSame($first->json('analysis_id'), $second->json('analysis_id'));
        $resent = $this->ai->calls[1];
        $this->assertSame($this->ai->calls[0]['candidates'], $resent['candidates']);
        $this->assertSame('shati', $resent['description']);
        $this->assertSame([['question' => 'Does it have sleeves?', 'answer' => 'Hapana']], $resent['clarifications']);

        $analysis = AiProductAnalysis::findOrFail($first->json('analysis_id'));
        $this->assertSame(1, $analysis->rounds);
        $this->assertSame('Hapana', $analysis->clarifications[0]['answer']);
    }

    public function test_the_loop_stops_after_two_rounds_and_returns_the_current_ranking(): void
    {
        $asking = fn () => ['status' => 'needs_clarification', 'ranked' => [['id' => 'CAT-0001', 'label' => 'Medium']],
            'clarification_questions' => ['Another question?']];
        $this->ai->push($asking)->push($asking)->push($asking)->push($asking);
        $answer = fn (int $id) => ['analysis_id' => $id, 'answers' => [['question' => 'Another question?', 'answer' => 'Ndiyo']]];

        $id = $this->identifyText('shati')->assertJsonPath('status', 'needs_clarification')->json('analysis_id');
        $this->postJson('/api/identify/clarify', $answer($id))->assertJsonPath('status', 'needs_clarification')->assertJsonPath('round', 1);

        // Round 2: the AI asks again, but no rounds are left, so the ranking is returned.
        $this->postJson('/api/identify/clarify', $answer($id))
            ->assertJsonPath('status', 'ok')->assertJsonPath('round', 2)
            ->assertJsonPath('clarification_questions', [])
            ->assertJsonPath('results.*.id', ['CAT-0001']);

        // A third answer does not reach the AI at all.
        $this->postJson('/api/identify/clarify', $answer($id))->assertJsonPath('status', 'ok')->assertJsonPath('ai_used', false);
        $this->assertCount(3, $this->ai->calls);
    }

    public function test_an_analysis_from_another_session_cannot_be_clarified_or_selected(): void
    {
        $this->ai->push(['status' => 'needs_clarification', 'ranked' => [['id' => 'CAT-0001']], 'clarification_questions' => ['Q?']]);
        $id = $this->identifyText('shati')->json('analysis_id');

        $this->flushSession();

        $this->postJson('/api/identify/clarify', ['analysis_id' => $id, 'answers' => [['question' => 'Q?', 'answer' => 'A']]])->assertNotFound();
        $this->postJson('/api/selection', ['catalogue_item_id' => 'CAT-0001', 'ai_product_analysis_id' => $id])
            ->assertStatus(422)->assertJsonValidationErrors('ai_product_analysis_id');
    }

    public function test_selection_records_the_confirmed_item_on_the_analysis(): void
    {
        $id = $this->identifyText()->json('analysis_id');

        $this->postJson('/api/selection', ['catalogue_item_id' => 'CAT-0004', 'ai_product_analysis_id' => $id])->assertOk();

        $this->assertSame('CAT-0004', AiProductAnalysis::find($id)->confirmed_catalogue_item_id);
    }

    public function test_text_identification_validates_its_input(): void
    {
        $this->postJson('/api/identify/text', [])->assertStatus(422)->assertJsonValidationErrors('description');
        $this->postJson('/api/identify/text', ['description' => 'rice', 'lang' => 'fr'])->assertStatus(422)->assertJsonValidationErrors('lang');
        $this->postJson('/api/identify/clarify', ['analysis_id' => 1, 'answers' => []])->assertStatus(422)->assertJsonValidationErrors('answers');
    }

    // --- photos ------------------------------------------------------------

    public function test_a_photo_is_sent_as_an_image_with_the_whole_catalogue_then_deleted(): void
    {
        $seenDuringCall = null;
        $this->ai->push(function (array $payload) use (&$seenDuringCall) {
            $seenDuringCall = $this->uploadsLeft();

            return $this->ok([['id' => 'CAT-0004', 'label' => 'High']]);
        });

        $response = $this->identifyImage($this->photo(), ['lang' => 'bem'])
            ->assertOk()->assertJsonPath('input_type', 'image')->assertJsonPath('results.0.id', 'CAT-0004');

        $sent = $this->ai->calls[0];
        $this->assertSame('image', $sent['input_type']);
        $this->assertSame('bem', $sent['language']);
        $this->assertStringStartsWith('data:image/png;base64,', $sent['image_ref']);
        $this->assertCount(self::USABLE_ITEMS, $sent['candidates']);

        $this->assertCount(1, $seenDuringCall, 'the photo should be on the private disk during the call');
        $this->assertStringNotContainsString('photo.png', $seenDuringCall[0], 'the stored name must be random');
        $this->assertSame([], $this->uploadsLeft(), 'the photo must be deleted after analysis');

        $analysis = AiProductAnalysis::find($response->json('analysis_id'));
        $this->assertStringNotContainsString('base64', json_encode($analysis->toArray()), 'no image data is stored');
    }

    public function test_the_photo_is_deleted_even_when_the_ai_fails(): void
    {
        $this->ai->push(new IdentificationFailed('timeout'));

        $this->identifyImage($this->photo())
            ->assertOk()
            ->assertJsonPath('status', 'error')
            ->assertJsonPath('results', [])
            ->assertJsonPath('message', ProductIdentificationService::PHOTO_FAILED);

        $this->assertSame([], $this->uploadsLeft());
    }

    public function test_no_match_on_a_photo_asks_the_trader_to_type_the_name(): void
    {
        $this->ai->push(['status' => 'no_match', 'ranked' => []]);

        $this->identifyImage($this->photo())->assertJsonPath('status', 'no_match')->assertJsonPath('message', ProductIdentificationService::PHOTO_FAILED);
    }

    public function test_a_renamed_exe_is_refused(): void
    {
        $exe = 'MZ'.str_repeat("\x90\x00", 30).'This program cannot be run in DOS mode.'.str_repeat("\x00", 200);

        $this->identifyImage($this->photo('photo.jpg', $exe))->assertStatus(422)->assertJsonValidationErrors('photo');
        $this->assertSame([], $this->ai->calls);
        $this->assertSame([], $this->uploadsLeft());
    }

    public function test_a_text_file_named_like_an_image_is_refused(): void
    {
        $this->identifyImage($this->photo('photo.png', "just some text\n"))->assertStatus(422)->assertJsonValidationErrors('photo');
    }

    public function test_a_photo_over_5_mb_is_refused(): void
    {
        $big = base64_decode(self::PNG).str_repeat("\x00", 5 * 1024 * 1024 + 1);

        $this->identifyImage($this->photo('big.png', $big))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['photo' => 'The photo must not be larger than 5 MB.']);
        $this->assertSame([], $this->ai->calls);
    }

    public function test_a_missing_photo_is_refused(): void
    {
        $this->postJson('/api/identify/image', [])->assertStatus(422)->assertJsonValidationErrors('photo');
    }
}
