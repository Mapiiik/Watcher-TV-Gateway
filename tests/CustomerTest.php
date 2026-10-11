<?php
declare(strict_types=1);

namespace WatcherTvGateway\Test;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use WatcherTvGateway\Customer;

#[CoversClass(Customer::class)]
final class CustomerTest extends TestCase
{
    public function testTheFullNameLeavesOutTheCompanyAndMissingParts(): void
    {
        $customer = new Customer('110009', title: 'Ing.', firstName: 'Jan', lastName: 'Novák', company: 'Firma');

        $this->assertSame('Ing. Jan Novák', $customer->fullName());
    }

    public function testAPlaceWithoutStreetsIsAddressedByTheHouseNumber(): void
    {
        $this->assertSame('Hlavní 12', (new Customer('1', street: 'Hlavní', houseNumber: '12'))->streetAddress());
        $this->assertSame('č.p. 299', (new Customer('1', houseNumber: '299'))->streetAddress());
        $this->assertSame('', (new Customer('1'))->streetAddress());
    }

    public function testACompanyIsMatchedByPartOfItsNameInAnyCase(): void
    {
        $customer = new Customer('1', company: 'Mopos Communications, a.s.');

        $this->assertTrue($customer->belongsToAnyOf(['MOPOS']));
        $this->assertFalse($customer->belongsToAnyOf(['NETAIR']));
        $this->assertFalse((new Customer('1'))->belongsToAnyOf(['MOPOS']));
    }
}
