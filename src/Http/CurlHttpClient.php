<?php
declare(strict_types=1);

namespace WatcherTvGateway\Http;

/**
 * Sends requests with curl. Redirects are not followed: an answer that sends us elsewhere is
 * an answer we did not expect.
 */
final readonly class CurlHttpClient implements HttpClient
{
    /**
     * @param int $timeout Seconds the whole request may take.
     * @param bool $verifyTls Whether the certificate has to be valid; off only against development servers.
     */
    public function __construct(
        private int $timeout = 15,
        private bool $verifyTls = true,
    ) {
    }

    public function send(HttpRequest $request): HttpResponse
    {
        $headers = [];
        foreach ($request->headers as $name => $value) {
            $headers[] = $name . ': ' . $value;
        }

        $handle = curl_init($request->url);
        curl_setopt_array($handle, [
            CURLOPT_CUSTOMREQUEST => $request->method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS | CURLPROTO_HTTP,
            CURLOPT_CONNECTTIMEOUT => min(5, $this->timeout),
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_SSL_VERIFYPEER => $this->verifyTls,
            CURLOPT_SSL_VERIFYHOST => $this->verifyTls ? 2 : 0,
        ]);

        $body = curl_exec($handle);
        if (!is_string($body)) {
            throw new HttpError(sprintf('%s %s: %s', $request->method, self::withoutQuery($request->url), curl_error($handle)));
        }

        return new HttpResponse((int)curl_getinfo($handle, CURLINFO_RESPONSE_CODE), $body);
    }

    /**
     * The address without its query, which may carry personal data.
     */
    private static function withoutQuery(string $url): string
    {
        return explode('?', $url, 2)[0];
    }
}
