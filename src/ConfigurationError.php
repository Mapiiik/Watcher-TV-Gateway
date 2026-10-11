<?php
declare(strict_types=1);

namespace WatcherTvGateway;

use RuntimeException;

/**
 * The gateway is missing a setting, or has one it cannot use.
 */
final class ConfigurationError extends RuntimeException
{
}
