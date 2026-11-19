<?php

namespace Tests\Feature\Identification;

use App\Services\Identification\AiResponseValidator;
use App\Services\Identification\InvalidAiResponse;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\TariffFixtures;
use Tests\TestCase;

class AiResponseValidatorTest extends TestCase
{
    use RefreshDatabase;
    use TariffFixtures;

    private const SENT = ['CAT-0001', 'CAT-0003', 'CAT-0004', 'CAT-0099'];

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-11-20 10:00', 'Africa/Lusaka'));
        $this->seedTariffFixtures();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function validate(array $body)
    {
        return (new AiResponseValidator)->validate($body + ['status' => 'ok'], self::SENT);
    }

    public static function malformedBodies(): array
    {
        return [
            'a string' => ['not json at all'],
            'a list' => [[['id' => 'CAT-0001']]],
            'null' => [null],
            'no status' => [['ranked' => []]],
            'unknown status' => [['status' => 'great']],
            'status as a number' => [['status' => 1]],
            'ranked not a list' => [['status' => 'ok', 'ranked' => ['a' => ['id' => 'CAT-0001']]]],
            'ranked a string' => [['status' => 'ok', 'ranked' => 'CAT-0001']],
        ];
    }

    #[DataProvider('malformedBodies')]
    public function test_a_body_outside_the_contract_is_refused(mixed $body): void
    {
        $this->expectException(InvalidAiResponse::class);

        (new AiResponseValidator)->validate($body, self::SENT);
    }

    public function test_each_agreed_status_is_accepted(): void
    {
        foreach (['ok', 'no_match', 'error'] as $status) {
            $this->assertContains((new AiResponseValidator)->validate(['status' => $status, 'ranked' => [['id' => 'CAT-0001', 'label' => 'High']]], self::SENT)->status, ['ok', 'no_match', 'error']);
        }
        $clarify = $this->validate(['status' => 'needs_clarification', 'ranked' => [['id' => 'CAT-0001']], 'clarification_questions' => ['Knitted or woven?']]);
        $this->assertSame('needs_clarification', $clarify->status);
        $this->assertSame(['Knitted or woven?'], $clarify->clarificationQuestions);
    }

    public function test_ids_not_sent_unknown_or_inactive_are_dropped_and_reported(): void
    {
        $result = $this->validate(['ranked' => [
            ['id' => 'CAT-9999', 'label' => 'High'],   // does not exist
            ['id' => 'CAT-0006', 'label' => 'High'],   // exists, but Laravel did not send it
            ['id' => 'CAT-0099', 'label' => 'High'],   // sent, but inactive
            ['id' => '61091000', 'label' => 'High'],   // an HS code, not a catalogue ID
            ['id' => 'CAT-0004', 'label' => 'Medium'],
            ['label' => 'High'],                       // no id at all
        ]]);

        $this->assertSame(['CAT-0004'], array_column($result->ranked, 'id'));
        $this->assertSame(['CAT-9999', 'CAT-0006', 'CAT-0099', '61091000', '{"label":"High"}'], $result->droppedIds);
    }

    public function test_ok_with_nothing_left_becomes_no_match(): void
    {
        $this->assertSame('no_match', $this->validate(['ranked' => [['id' => 'CAT-9999', 'label' => 'High']]])->status);
        $this->assertSame('no_match', $this->validate(['ranked' => []])->status);
    }

    public function test_an_unknown_label_becomes_low_and_duplicates_keep_their_first_place(): void
    {
        $result = $this->validate(['ranked' => [
            ['id' => 'CAT-0003', 'label' => 'Very high', 'reason' => 'looks like rice'],
            ['id' => 'CAT-0001', 'label' => 'High'],
            ['id' => 'CAT-0003', 'label' => 'High'],
        ]]);

        $this->assertSame([
            ['id' => 'CAT-0003', 'label' => 'Low', 'reason' => 'looks like rice'],
            ['id' => 'CAT-0001', 'label' => 'High', 'reason' => null],
        ], $result->ranked);
    }

    public function test_needs_clarification_without_questions_becomes_ok(): void
    {
        $result = $this->validate(['status' => 'needs_clarification', 'ranked' => [['id' => 'CAT-0001', 'label' => 'High']], 'clarification_questions' => ['', 42]]);

        $this->assertSame('ok', $result->status);
        $this->assertSame([], $result->clarificationQuestions);
    }

    public function test_questions_are_trimmed_and_capped_at_three(): void
    {
        $result = $this->validate(['status' => 'needs_clarification', 'ranked' => [['id' => 'CAT-0001']],
            'clarification_questions' => [' One? ', 'Two?', 'Three?', 'Four?']]);

        $this->assertSame(['One?', 'Two?', 'Three?'], $result->clarificationQuestions);
    }

    public function test_manual_review_must_be_literally_true_and_attributes_must_be_scalar(): void
    {
        $result = $this->validate(['ranked' => [['id' => 'CAT-0001']], 'manual_review' => 'yes',
            'attributes' => ['material' => 'cotton', 'colours' => ['red', 'blue'], 'weight_kg' => 1.5]]);

        $this->assertFalse($result->manualReview);
        $this->assertSame(['material' => 'cotton', 'weight_kg' => 1.5], $result->attributes);
        $this->assertTrue($this->validate(['ranked' => [['id' => 'CAT-0001']], 'manual_review' => true])->manualReview);
    }
}
