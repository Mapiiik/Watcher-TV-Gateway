<?php
declare(strict_types=1);

namespace WatcherTvGateway\Provider;

/**
 * What a provider made of a request: where to send the customer, or why nowhere.
 */
final readonly class ProviderAnswer
{
    private function __construct(
        public ProviderOutcome $outcome,
        public string $url = '',
        public string $reason = '',
    ) {
    }

    public static function redirect(string $url): self
    {
        return new self(ProviderOutcome::Redirect, url: $url);
    }

    public static function unknownCustomer(): self
    {
        return new self(ProviderOutcome::UnknownCustomer);
    }

    /**
     * @param string $reason For the log, never shown to the customer.
     */
    public static function unavailable(string $reason): self
    {
        return new self(ProviderOutcome::Unavailable, reason: $reason);
    }
}
