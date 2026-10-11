<?php
declare(strict_types=1);

namespace WatcherTvGateway\Http;

final readonly class HttpResponse
{
    public function __construct(
        public int $status,
        public string $body,
    ) {
    }

    /**
     * The body as a JSON object, or null when it is not one.
     *
     * @return array<string, mixed>|null
     */
    public function json(): ?array
    {
        $data = json_decode($this->body, true);
        if (!is_array($data)) {
            return null;
        }

        $object = [];
        foreach ($data as $key => $value) {
            $object[(string)$key] = $value;
        }

        return $object;
    }
}
