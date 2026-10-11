<?php
declare(strict_types=1);

namespace WatcherTvGateway\Test\Support;

use WatcherTvGateway\Customer;
use WatcherTvGateway\Provider\ProviderAnswer;
use WatcherTvGateway\Provider\TvProvider;

/**
 * A provider that answers what a test lines up, and keeps who it was asked about.
 */
final class FakeProvider implements TvProvider
{
    /**
     * @var list<string>
     */
    public array $signedIn = [];

    public function __construct(
        private ProviderAnswer $answer,
    ) {
    }

    public function name(): string
    {
        return 'TestTV';
    }

    public function signIn(Customer $customer, string $ip): ProviderAnswer
    {
        $this->signedIn[] = $customer->number . '@' . $ip;

        return $this->answer;
    }

    public function playlist(Customer $customer): ProviderAnswer
    {
        return $this->answer;
    }
}
