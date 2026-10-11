<?php
declare(strict_types=1);

namespace WatcherTvGateway\Http;

final readonly class HttpRequest
{
    /**
     * @param non-empty-string $method
     * @param array<string, string> $headers Header names and values.
     */
    public function __construct(
        public string $method,
        public string $url,
        public array $headers = [],
    ) {
    }
}
