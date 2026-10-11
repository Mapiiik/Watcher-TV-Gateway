<?php
declare(strict_types=1);

namespace WatcherTvGateway\Test\Support;

use WatcherTvGateway\Http\HttpClient;
use WatcherTvGateway\Http\HttpError;
use WatcherTvGateway\Http\HttpRequest;
use WatcherTvGateway\Http\HttpResponse;

/**
 * Answers with what a test lined up, and keeps what was asked.
 */
final class FakeHttpClient implements HttpClient
{
    /**
     * @var list<\WatcherTvGateway\Http\HttpRequest>
     */
    public array $requests = [];

    /**
     * @var list<\WatcherTvGateway\Http\HttpResponse|\WatcherTvGateway\Http\HttpError>
     */
    private array $answers = [];

    public function answer(int $status, string $body): self
    {
        $this->answers[] = new HttpResponse($status, $body);

        return $this;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function answerJson(int $status, array $data): self
    {
        return $this->answer($status, (string)json_encode($data));
    }

    public function fail(string $message): self
    {
        $this->answers[] = new HttpError($message);

        return $this;
    }

    public function send(HttpRequest $request): HttpResponse
    {
        $this->requests[] = $request;
        $answer = array_shift($this->answers) ?? new HttpError('No answer lined up.');
        if ($answer instanceof HttpError) {
            throw $answer;
        }

        return $answer;
    }

    /**
     * The query of the last request.
     *
     * @return array<int|string, mixed>
     */
    public function lastQuery(): array
    {
        $request = $this->requests[array_key_last($this->requests) ?? 0] ?? null;
        parse_str((string)parse_url((string)$request?->url, PHP_URL_QUERY), $query);

        return $query;
    }
}
