<?php
declare(strict_types=1);

namespace WatcherTvGateway\Test\Crm;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use WatcherTvGateway\Crm\CrmUnavailable;
use WatcherTvGateway\Crm\WatcherCrm;
use WatcherTvGateway\Environment;
use WatcherTvGateway\Test\Support\FakeHttpClient;

#[CoversClass(WatcherCrm::class)]
final class WatcherCrmTest extends TestCase
{
    private FakeHttpClient $http;

    private WatcherCrm $crm;

    protected function setUp(): void
    {
        $this->http = new FakeHttpClient();
        $this->crm = WatcherCrm::fromEnvironment(new Environment([
            'CRM_API_URL' => 'https://crm.example.net/api/customer-portal/',
            'CRM_API_KEY' => 'portal-key',
        ]), $this->http);
    }

    public function testTheCrmIsAskedWithTheKeyAboutTheAddress(): void
    {
        $this->http->answerJson(200, ['customer' => null]);

        $this->crm->findByAddress('192.0.2.10');

        $request = $this->http->requests[0];
        $this->assertSame('GET', $request->method);
        $this->assertSame('https://crm.example.net/api/customer-portal/identify?ip=192.0.2.10', $request->url);
        $this->assertSame('Bearer portal-key', $request->headers['Authorization']);
        $this->assertSame('application/json', $request->headers['Accept']);
    }

    public function testACustomerWithAnInstallationAddressIsFound(): void
    {
        $this->http->answerJson(200, ['customer' => [
            'id' => 'a1bf2e62-2ab2-4a99-8baf-2cf5bc6d4fa8',
            'number' => '110009',
            'name' => 'Jan Novák',
            'title' => null,
            'first_name' => 'Jan',
            'last_name' => 'Novák',
            'suffix' => '',
            'company' => 'NETAIR, s.r.o.',
            'email' => 'jan@example.com',
            'address' => ['street' => 'Hlavní', 'number' => '12', 'city' => 'Jablonec nad Jizerou', 'zip' => '512 43'],
        ]]);

        $customer = $this->crm->findByAddress('192.0.2.10');

        $this->assertNotNull($customer);
        $this->assertSame('110009', $customer->number);
        $this->assertSame('Jan Novák', $customer->fullName());
        $this->assertNull($customer->suffix);
        $this->assertSame('jan@example.com', $customer->email);
        $this->assertSame('Hlavní 12', $customer->streetAddress());
        $this->assertSame('Jablonec nad Jizerou', $customer->city);
        $this->assertSame('512 43', $customer->postcode);
    }

    public function testNobodyAndACustomerWithoutAnInstallationAddressAreNotFound(): void
    {
        $this->http
            ->answerJson(200, ['customer' => null])
            ->answerJson(200, ['customer' => ['number' => '110009', 'address' => null]]);

        $this->assertNull($this->crm->findByAddress('192.0.2.10'));
        $this->assertNull($this->crm->findByAddress('192.0.2.10'));
    }

    public function testARefusedKeyIsReportedAsSuch(): void
    {
        $this->http->answer(302, '');

        $this->expectException(CrmUnavailable::class);
        $this->expectExceptionMessage('HTTP 302, check CRM_API_KEY and its role');
        $this->crm->findByAddress('192.0.2.10');
    }

    public function testTooManyLookupsAreReportedAsSuch(): void
    {
        $this->http->answer(429, '{"message":"Too many lookups, try again in a minute."}');

        $this->expectException(CrmUnavailable::class);
        $this->expectExceptionMessage('HTTP 429, too many lookups in a minute');
        $this->crm->findByAddress('192.0.2.10');
    }

    public function testNoAnswerIsReportedAsTheCrmBeingUnavailable(): void
    {
        $this->http->fail('Connection refused');

        $this->expectException(CrmUnavailable::class);
        $this->expectExceptionMessage('Connection refused');
        $this->crm->findByAddress('192.0.2.10');
    }

    public function testAnAnswerThatIsNotTheExpectedOneIsRefused(): void
    {
        $this->http->answer(200, '<html>maintenance</html>');

        $this->expectException(CrmUnavailable::class);
        $this->crm->findByAddress('192.0.2.10');
    }
}
