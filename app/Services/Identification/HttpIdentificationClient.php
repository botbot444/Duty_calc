<?php

namespace App\Services\Identification;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

/**
 * Calls the AI engineer's service over HTTPS: 15 s timeout, one retry on a connection
 * failure or timeout. The API key comes from .env through config/services.php only.
 */
class HttpIdentificationClient implements IdentificationClient
{
    public function __construct(private readonly array $config) {}

    public function identify(array $payload): mixed
    {
        if (empty($this->config['url'])) {
            throw new IdentificationFailed('not_configured', 'IDENTIFICATION_URL is not set');
        }

        try {
            $response = Http::acceptJson()
                ->asJson()
                ->withToken((string) $this->config['key'])
                ->timeout((int) $this->config['timeout'])
                ->retry(
                    1 + (int) $this->config['retries'],
                    200,
                    fn (\Throwable $e) => $e instanceof ConnectionException,
                    throw: false,
                )
                ->post($this->config['url'], $payload);
        } catch (ConnectionException $e) {
            throw new IdentificationFailed('timeout', $e->getMessage(), $e);
        }

        try {
            $response->throw();
        } catch (RequestException $e) {
            throw new IdentificationFailed('http_'.$response->status(), $e->getMessage(), $e);
        }

        $decoded = json_decode($response->body(), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new IdentificationFailed('bad_json', json_last_error_msg());
        }

        return $decoded;
    }

    public function modelName(): ?string
    {
        return $this->config['model'] ?? null;
    }
}
