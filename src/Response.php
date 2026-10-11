<?php
declare(strict_types=1);

namespace WatcherTvGateway;

final readonly class Response
{
    /**
     * @param array<string, string> $headers Header names and values.
     */
    public function __construct(
        public int $status,
        public array $headers = [],
        public string $body = '',
    ) {
    }

    /**
     * Sends the client elsewhere.
     */
    public static function redirect(string $url, int $status = 303): self
    {
        return new self($status, ['Location' => $url]);
    }

    public static function text(int $status, string $text): self
    {
        return new self($status, ['Content-Type' => 'text/plain; charset=UTF-8'], $text);
    }

    /**
     * Sends the response to the client.
     */
    public function send(): void
    {
        http_response_code($this->status);
        // what we answer depends on who asks, so nothing on the way may keep it
        header('Cache-Control: no-store');
        foreach ($this->headers as $name => $value) {
            header($name . ': ' . $value);
        }
        echo $this->body;
    }
}
