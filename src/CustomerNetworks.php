<?php
declare(strict_types=1);

namespace WatcherTvGateway;

/**
 * The networks customers connect from.
 *
 * A customer is recognised by an address of the operator's, so an address anywhere else needs no
 * lookup. The CRM limits the addresses the gateway may ask about in a minute, and visitors from
 * the internet would otherwise use that up for the customers. Without networks every address counts.
 */
final readonly class CustomerNetworks
{
    /**
     * @var list<array{address: string, bits: int}>
     */
    private array $networks;

    /**
     * @param list<string> $networks Networks in CIDR notation, IPv4 or IPv6.
     * @throws \WatcherTvGateway\ConfigurationError When a network cannot be read.
     */
    public function __construct(array $networks = [])
    {
        $this->networks = array_map(self::parse(...), $networks);
    }

    /**
     * Whether a customer can be connected at the address.
     */
    public function contains(string $ip): bool
    {
        if ($this->networks === []) {
            return true;
        }
        if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
            return false;
        }
        $address = (string)inet_pton($ip);

        foreach ($this->networks as $network) {
            if (self::isIn($address, $network['address'], $network['bits'])) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{address: string, bits: int}
     * @throws \WatcherTvGateway\ConfigurationError When the network cannot be read.
     */
    private static function parse(string $network): array
    {
        [$ip, $bits] = array_pad(explode('/', $network, 2), 2, '');
        $address = filter_var($ip, FILTER_VALIDATE_IP) === false ? '' : (string)inet_pton($ip);
        if ($address === '' || !ctype_digit($bits) || (int)$bits > strlen($address) * 8) {
            throw new ConfigurationError(sprintf('CUSTOMER_NETWORKS: %s is not a network in CIDR notation.', $network));
        }

        return ['address' => $address, 'bits' => (int)$bits];
    }

    /**
     * Compares the addresses bit by bit as far as the mask of the network goes.
     */
    private static function isIn(string $address, string $network, int $bits): bool
    {
        if (strlen($address) !== strlen($network)) {
            return false;
        }

        $bytes = intdiv($bits, 8);
        if (substr($address, 0, $bytes) !== substr($network, 0, $bytes)) {
            return false;
        }

        $mask = 0xff & (0xff << (8 - $bits % 8));

        return $bits % 8 === 0 || (ord($address[$bytes]) & $mask) === (ord($network[$bytes]) & $mask);
    }
}
