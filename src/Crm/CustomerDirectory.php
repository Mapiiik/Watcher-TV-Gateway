<?php
declare(strict_types=1);

namespace WatcherTvGateway\Crm;

use WatcherTvGateway\Customer;

/**
 * Where the gateway finds out who is connected at an address.
 */
interface CustomerDirectory
{
    /**
     * The customer whose connection uses the address, or null when nobody's does.
     *
     * @throws \WatcherTvGateway\Crm\CrmUnavailable When the directory cannot be asked.
     */
    public function findByAddress(string $ip): ?Customer;
}
