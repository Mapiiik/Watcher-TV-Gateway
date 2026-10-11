<?php
declare(strict_types=1);

namespace WatcherTvGateway\Test\Provider;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use WatcherTvGateway\ConfigurationError;
use WatcherTvGateway\Environment;
use WatcherTvGateway\Provider\Providers;
use WatcherTvGateway\Provider\SledovaniTv;
use WatcherTvGateway\Test\Support\FakeHttpClient;

#[CoversClass(Providers::class)]
final class ProvidersTest extends TestCase
{
    public function testTheProviderIsPickedByName(): void
    {
        $provider = Providers::fromEnvironment(new Environment([
            'TV_PROVIDER' => 'SledovaniTV',
            'SLEDOVANITV_PARTNER' => 'netair',
            'SLEDOVANITV_PASSWORD' => 'secret',
        ]), new FakeHttpClient());

        $this->assertInstanceOf(SledovaniTv::class, $provider);
    }

    public function testAProviderNobodyKnowsIsRefused(): void
    {
        $this->expectException(ConfigurationError::class);
        $this->expectExceptionMessage('TV_PROVIDER "elsewhere" is not one this gateway knows (sledovanitv).');

        Providers::fromEnvironment(new Environment(['TV_PROVIDER' => 'elsewhere']), new FakeHttpClient());
    }

    public function testTheProvidersOwnSettingsAreRequired(): void
    {
        $this->expectException(ConfigurationError::class);
        $this->expectExceptionMessage('SLEDOVANITV_PARTNER is not set.');

        Providers::fromEnvironment(new Environment(['TV_PROVIDER' => 'sledovanitv']), new FakeHttpClient());
    }
}
