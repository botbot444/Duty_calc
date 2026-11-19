<?php

namespace Tests\Feature\Identification;

use App\Services\Identification\FakeIdentificationClient;
use App\Services\Identification\HttpIdentificationClient;
use App\Services\Identification\IdentificationClient;
use App\Services\Identification\IdentificationFailed;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The real HTTP client, against Http::fake(): no request ever leaves the machine.
 */
class HttpIdentificationClientTest extends TestCase
{
    private const URL = 'https://ai.example.test/identify';

    private function client(array $overrides = []): HttpIdentificationClient
    {
        return new HttpIdentificationClient($overrides + [
            'url' => self::URL, 'key' => 'test-key-not-real', 'model' => 'test-model', 'timeout' => 15, 'retries' => 1,
        ]);
    }

    private function failure(callable $call): IdentificationFailed
    {
        try {
            $call();
        } catch (IdentificationFailed $e) {
            return $e;
        }
        $this->fail('Expected IdentificationFailed');
    }

    public function test_it_posts_the_payload_with_the_key_as_a_bearer_token(): void
    {
        Http::fake([self::URL => Http::response(['status' => 'ok', 'ranked' => []])]);
        $payload = ['input_type' => 'text', 'description' => 'sugar', 'language' => 'en', 'image_ref' => null,
            'candidates' => [['id' => 'CAT-0004', 'hs_code' => '17019900', 'description' => 'Sugar']], 'clarifications' => []];

        $body = $this->client()->identify($payload);

        $this->assertSame(['status' => 'ok', 'ranked' => []], $body);
        Http::assertSent(fn (Request $r) => $r->url() === self::URL
            && $r->method() === 'POST'
            && $r->hasHeader('Authorization', 'Bearer test-key-not-real')
            && $r->data() === $payload
            && ! str_contains($r->body(), 'test-key-not-real'));
    }

    public function test_a_server_error_is_a_failure(): void
    {
        Http::fake([self::URL => Http::response('oops', 500)]);

        $this->assertSame('http_500', $this->failure(fn () => $this->client()->identify([]))->reason);
    }

    public function test_a_body_that_is_not_json_is_a_failure(): void
    {
        Http::fake([self::URL => Http::response('<html>Bad gateway</html>', 200)]);

        $this->assertSame('bad_json', $this->failure(fn () => $this->client()->identify([]))->reason);
    }

    public function test_a_timeout_is_retried_once_then_fails(): void
    {
        $attempts = 0;
        Http::fake(function () use (&$attempts) {
            $attempts++;
            throw new ConnectionException('cURL error 28: Operation timed out');
        });

        $this->assertSame('timeout', $this->failure(fn () => $this->client()->identify([]))->reason);
        $this->assertSame(2, $attempts);
    }

    public function test_a_timeout_followed_by_success_recovers(): void
    {
        $attempts = 0;
        Http::fake(function () use (&$attempts) {
            if (++$attempts === 1) {
                throw new ConnectionException('timed out');
            }

            return Http::response(['status' => 'no_match']);
        });

        $this->assertSame(['status' => 'no_match'], $this->client()->identify([]));
    }

    public function test_a_missing_url_fails_without_any_request(): void
    {
        Http::fake();

        $this->assertSame('not_configured', $this->failure(fn () => $this->client(['url' => null])->identify([]))->reason);
        Http::assertNothingSent();
    }

    public function test_the_driver_setting_picks_the_client(): void
    {
        config(['services.identification.driver' => 'fake']);
        $this->app->forgetInstance(IdentificationClient::class);
        $this->assertInstanceOf(FakeIdentificationClient::class, $this->app->make(IdentificationClient::class));

        config(['services.identification.driver' => 'http']);
        $this->app->forgetInstance(IdentificationClient::class);
        $this->assertInstanceOf(HttpIdentificationClient::class, $this->app->make(IdentificationClient::class));
    }

    public function test_tests_and_local_development_default_to_the_fake_client(): void
    {
        $this->assertSame('fake', config('services.identification.driver'));
    }
}
