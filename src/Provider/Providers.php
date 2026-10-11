<?php
declare(strict_types=1);

namespace WatcherTvGateway\Provider;

use WatcherTvGateway\ConfigurationError;
use WatcherTvGateway\Environment;
use WatcherTvGateway\Http\HttpClient;

/**
 * The TV providers the gateway knows, by the name TV_PROVIDER picks them with.
 */
final class Providers
{
    /**
     * Names TV_PROVIDER accepts.
     *
     * @var list<string>
     */
    public const array NAMES = ['sledovanitv'];

    /**
     * The provider TV_PROVIDER names, set up from its own settings.
     *
     * @throws \WatcherTvGateway\ConfigurationError When the name or the provider's settings are wrong.
     */
    public static function fromEnvironment(Environment $env, HttpClient $http): TvProvider
    {
        $name = strtolower($env->required('TV_PROVIDER'));

        return match ($name) {
            'sledovanitv' => SledovaniTv::fromEnvironment($env, $http),
            default => throw new ConfigurationError(sprintf(
                'TV_PROVIDER "%s" is not one this gateway knows (%s).',
                $name,
                implode(', ', self::NAMES),
            )),
        };
    }
}
