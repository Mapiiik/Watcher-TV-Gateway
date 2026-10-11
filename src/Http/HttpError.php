<?php
declare(strict_types=1);

namespace WatcherTvGateway\Http;

use RuntimeException;

/**
 * A request got no answer: the connection, TLS or the time limit failed.
 */
final class HttpError extends RuntimeException
{
}
