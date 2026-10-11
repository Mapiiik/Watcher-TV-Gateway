<?php
declare(strict_types=1);

namespace WatcherTvGateway\Test\Support;

use WatcherTvGateway\Crm\CrmUnavailable;
use WatcherTvGateway\Crm\CustomerDirectory;
use WatcherTvGateway\Customer;

/**
 * Customers by address, as a test sets them up.
 */
final class FakeDirectory implements CustomerDirectory
{
    /**
     * @var list<string>
     */
    public array $asked = [];

    /**
     * @param array<string, \WatcherTvGateway\Customer> $customers
     */
    public function __construct(
        private array $customers = [],
        private bool $unavailable = false,
    ) {
    }

    public function findByAddress(string $ip): ?Customer
    {
        $this->asked[] = $ip;
        if ($this->unavailable) {
            throw new CrmUnavailable('Watcher CRM did not answer.');
        }

        return $this->customers[$ip] ?? null;
    }
}
