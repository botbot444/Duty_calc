<?php

namespace Tests\Feature\Http;

use App\Models\AiProductAnalysis;
use App\Models\Calculation;
use App\Services\Identification\FakeIdentificationClient;
use App\Services\Identification\IdentificationClient;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\TariffFixtures;
use Tests\TestCase;

/**
 * B6 gate: the photo route reaches the same selection step and the same engine as the text
 * route, and gives the same estimate for the same item and inputs.
 */
class PhotoJourneyTest extends TestCase
{
    use RefreshDatabase;   // the photo route sends the whole small catalogue; no FULLTEXT needed
    use TariffFixtures;

    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

    private FakeIdentificationClient $ai;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-11-20 10:00', 'Africa/Lusaka'));
        Storage::fake('local');
        $this->seedTariffFixtures();
        $this->ai = new FakeIdentificationClient;
        $this->app->instance(IdentificationClient::class, $this->ai);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function tradeDetails(): array
    {
        return ['catalogue_item_id' => 'CAT-0004', 'trade_route_id' => $this->nakonde->id, 'declared_value' => '10000',
            'currency' => 'ZMW', 'freight' => '0', 'insurance' => '0', 'quantity' => '500'];
    }

    public function test_photo_then_clarify_then_select_then_estimate_matches_the_text_route(): void
    {
        $this->ai->push(['status' => 'needs_clarification', 'ranked' => [['id' => 'CAT-0004', 'label' => 'Medium'], ['id' => 'CAT-0003', 'label' => 'Low']],
            'clarification_questions' => ['Is it white or brown?']]);
        $this->ai->push(['status' => 'ok', 'ranked' => [['id' => 'CAT-0004', 'label' => 'High']]]);

        // 1. Photo.
        $photo = UploadedFile::fake()->createWithContent('sugar.png', base64_decode(self::PNG));
        $analysisId = $this->post('/api/identify/image', ['photo' => $photo, 'lang' => 'sw'], ['Accept' => 'application/json'])
            ->assertOk()->assertJsonPath('status', 'needs_clarification')->json('analysis_id');

        // 2. Answer the clarification question.
        $this->postJson('/api/identify/clarify', ['analysis_id' => $analysisId, 'answers' => [['question' => 'Is it white or brown?', 'answer' => 'Nyeupe']]])
            ->assertOk()->assertJsonPath('results.0.id', 'CAT-0004')->assertJsonPath('results.0.label', 'High');
        $this->assertNull($this->ai->calls[1]['image_ref'], 'the photo was deleted after the first round');

        // 3. The same selection step as the text route, with the analysis attached.
        $this->postJson('/api/selection', ['catalogue_item_id' => 'CAT-0004', 'ai_product_analysis_id' => $analysisId])
            ->assertOk()->assertJsonPath('required_inputs.quantity.unit', 'kg');

        // 4. The same engine.
        $photoEstimate = $this->postJson('/api/estimates', $this->tradeDetails())
            ->assertCreated()->assertJsonPath('status', 'estimated')->assertJsonPath('total_estimated_charges', '6240.00');

        $calc = Calculation::findOrFail($photoEstimate->json('estimate_id'));
        $this->assertSame($analysisId, $calc->ai_product_analysis_id);
        $this->assertSame('CAT-0004', AiProductAnalysis::find($analysisId)->confirmed_catalogue_item_id);
        $this->assertSame([], Storage::disk('local')->allFiles('identification-uploads'));

        // 5. Text route for the same item and inputs: identical charges, no analysis attached.
        $this->postJson('/api/selection', ['catalogue_item_id' => 'CAT-0004'])->assertOk();
        $textEstimate = $this->postJson('/api/estimates', $this->tradeDetails())->assertCreated();

        $this->assertSame($photoEstimate->json('line_items'), $textEstimate->json('line_items'));
        $this->assertSame($photoEstimate->json('total_estimated_charges'), $textEstimate->json('total_estimated_charges'));
        $this->assertNull(Calculation::findOrFail($textEstimate->json('estimate_id'))->ai_product_analysis_id);
    }

    public function test_old_analyses_are_pruned_unless_an_estimate_still_uses_them(): void
    {
        $this->ai->push(['status' => 'ok', 'ranked' => [['id' => 'CAT-0004', 'label' => 'High']]]);
        $photo = UploadedFile::fake()->createWithContent('sugar.png', base64_decode(self::PNG));
        $usedId = $this->post('/api/identify/image', ['photo' => $photo], ['Accept' => 'application/json'])->json('analysis_id');
        $this->postJson('/api/selection', ['catalogue_item_id' => 'CAT-0004', 'ai_product_analysis_id' => $usedId]);
        $calcId = $this->postJson('/api/estimates', $this->tradeDetails())->json('estimate_id');

        $unused = AiProductAnalysis::create(['input_type' => 'text', 'language' => 'en', 'product_attributes' => [], 'candidates_sent' => [],
            'ranked' => [], 'clarifications' => [], 'dropped_ids' => [], 'status' => 'no_match']);

        // 100 days on, but keep the estimate alive so its analysis must stay.
        Calculation::whereKey($calcId)->update(['expires_at' => '2027-12-31 00:00:00']);
        Carbon::setTestNow(Carbon::parse('2027-03-01 10:00', 'Africa/Lusaka'));

        $this->artisan('estimates:prune')->expectsOutputToContain('Deleted 1 old product analysis record(s).')->assertSuccessful();

        $this->assertNull(AiProductAnalysis::find($unused->id));
        $this->assertNotNull(AiProductAnalysis::find($usedId));
    }
}
