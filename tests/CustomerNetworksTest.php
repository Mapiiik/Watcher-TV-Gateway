<?php
declare(strict_types=1);

namespace WatcherTvGateway\Test;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use WatcherTvGateway\ConfigurationError;
use WatcherTvGateway\CustomerNetworks;

#[CoversClass(CustomerNetworks::class)]
final class CustomerNetworksTest extends TestCase
{
    public function testWithoutNetworksEveryAddressCounts(): void
    {
        $networks = new CustomerNetworks();

        $this->assertTrue($networks->contains('198.51.100.7'));
        $this->assertTrue($networks->contains('2001:db8::1'));
    }

    public function testAMaskCanEndInsideAByte(): void
    {
        $networks = new CustomerNetworks(['79.98.152.0/21']);

        $this->assertTrue($networks->contains('79.98.152.0'));
        $this->assertTrue($networks->contains('79.98.159.255'));
        $this->assertFalse($networks->contains('79.98.151.255'));
        $this->assertFalse($networks->contains('79.98.160.0'));
    }

    public function testAnyOfTheNetworksWillDo(): void
    {
        $networks = new CustomerNetworks(['192.0.2.0/24', '172.16.0.0/12']);

        $this->assertTrue($networks->contains('192.0.2.200'));
        $this->assertTrue($networks->contains('172.26.100.14'));
        $this->assertFalse($networks->contains('172.32.0.1'));
    }

    public function testAnIpv6NetworkHoldsIpv6Addresses(): void
    {
        $networks = new CustomerNetworks(['2a02:3c0::/29']);

        $this->assertTrue($networks->contains('2a02:3c0::1'));
        $this->assertTrue($networks->contains('2a02:3c7:ffff::1'));
        $this->assertFalse($networks->contains('2a02:3c8::1'));
    }

    public function testTheTwoFamiliesDoNotMix(): void
    {
        $this->assertFalse((new CustomerNetworks(['0.0.0.0/0']))->contains('2001:db8::1'));
        $this->assertFalse((new CustomerNetworks(['::/0']))->contains('192.0.2.1'));
    }

    public function testASingleAddressIsANetworkOfItsOwn(): void
    {
        $networks = new CustomerNetworks(['192.0.2.1/32']);

        $this->assertTrue($networks->contains('192.0.2.1'));
        $this->assertFalse($networks->contains('192.0.2.2'));
    }

    public function testSomethingThatIsNotAnAddressIsInNoNetwork(): void
    {
        $this->assertFalse((new CustomerNetworks(['0.0.0.0/0']))->contains('nowhere'));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function badNetworks(): array
    {
        return [
            'not a network' => ['nonsense'],
            'no mask' => ['192.0.2.0'],
            'a mask too long' => ['192.0.2.0/33'],
            'an IPv6 mask too long' => ['2a02:3c0::/129'],
            'a mask that is no number' => ['192.0.2.0/x'],
            'an empty mask' => ['192.0.2.0/'],
        ];
    }

    #[DataProvider('badNetworks')]
    public function testANetworkThatCannotBeReadIsAMistakeInTheSettings(string $network): void
    {
        $this->expectException(ConfigurationError::class);
        $this->expectExceptionMessage($network);

        new CustomerNetworks([$network]);
    }
}
