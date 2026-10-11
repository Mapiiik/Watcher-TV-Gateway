<?php
declare(strict_types=1);

namespace WatcherTvGateway\Http;

/**
 * Sends a request and hands back whatever answer came, of any status.
 */
interface HttpClient
{
    /**
     * @throws \WatcherTvGateway\Http\HttpError When no answer came at all.
     */
    public function send(HttpRequest $request): HttpResponse;
}
