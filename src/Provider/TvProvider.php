<?php
declare(strict_types=1);

namespace WatcherTvGateway\Provider;

use WatcherTvGateway\Customer;

/**
 * A TV service the gateway signs the operator's customers in to, as its authentication proxy.
 *
 * The customer number is the customer's identity at the provider. A new provider implements
 * this interface and is added to {@see Providers}.
 */
interface TvProvider
{
    /**
     * The service's name, as customers know it.
     */
    public function name(): string;

    /**
     * Where to send a customer to be signed in, or to register when they are new there.
     *
     * @param \WatcherTvGateway\Customer $customer The customer recognised by their address.
     * @param string $ip The address they came from.
     */
    public function signIn(Customer $customer, string $ip): ProviderAnswer;

    /**
     * Where a player finds the customer's channel playlist.
     */
    public function playlist(Customer $customer): ProviderAnswer;
}
