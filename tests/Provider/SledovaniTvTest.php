<?php
declare(strict_types=1);

namespace WatcherTvGateway\Test\Provider;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use WatcherTvGateway\Customer;
use WatcherTvGateway\Environment;
use WatcherTvGateway\Provider\ProviderAnswer;
use WatcherTvGateway\Provider\ProviderOutcome;
use WatcherTvGateway\Provider\SledovaniTv;
use WatcherTvGateway\Test\Support\FakeHttpClient;

#[CoversClass(SledovaniTv::class)]
#[CoversClass(ProviderAnswer::class)]
final class SledovaniTvTest extends TestCase
{
    private FakeHttpClient $http;

    private SledovaniTv $provider;

    private Customer $customer;

    protected function setUp(): void
    {
        $this->http = new FakeHttpClient();
        $this->provider = SledovaniTv::fromEnvironment(new Environment([
            'SLEDOVANITV_PARTNER' => 'netair',
            'SLEDOVANITV_PASSWORD' => 'secret',
        ]), $this->http);
        $this->customer = new Customer(
            '110009',
            firstName: 'Jan',
            lastName: 'Novák',
            email: 'jan@example.com',
            houseNumber: '299',
            city: 'Jablonec nad Jizerou',
            postcode: '512 43',
        );
    }

    public function testTheCustomerIsIdentifiedWithThePartnerSignedInByBasicAuth(): void
    {
        $this->http->answerJson(200, ['status' => 1, 'action' => 'login', 'url' => 'https://sledovanitv.cz/partner-login?token=x']);

        $answer = $this->provider->signIn($this->customer, '192.0.2.10');

        $this->assertSame(ProviderOutcome::Redirect, $answer->outcome);
        $this->assertSame('https://sledovanitv.cz/partner-login?token=x', $answer->url);
        $request = $this->http->requests[0];
        $this->assertStringStartsWith('https://sledovanitv.cz/partner/api/identify-user?', $request->url);
        $this->assertSame('Basic ' . base64_encode('netair:secret'), $request->headers['Authorization']);
        $this->assertSame([
            'partnerid' => '110009',
            'email' => 'jan@example.com',
            'fullName' => 'Jan Novák',
            'address' => 'č.p. 299',
            'city' => 'Jablonec nad Jizerou',
            'postcode' => '512 43',
            'ip' => '192.0.2.10',
        ], $this->http->lastQuery());
        $this->assertStringNotContainsString('secret', $request->url);
    }

    public function testAnIpv6AddressAndMissingDetailsAreLeftOut(): void
    {
        $this->http->answerJson(200, ['status' => 1, 'url' => 'https://example.com/']);

        $this->provider->signIn(new Customer('110009'), '2001:db8::1');

        $this->assertSame(['partnerid' => '110009'], $this->http->lastQuery());
    }

    public function testThePlaylistOfAKnownAndAnUnknownCustomer(): void
    {
        $this->http
            ->answerJson(200, ['status' => 1, 'url' => 'https://sledovanitv.cz/playlist.m3u'])
            ->answerJson(200, ['status' => 0, 'error' => 'unknown user']);

        $known = $this->provider->playlist($this->customer);
        $unknown = $this->provider->playlist($this->customer);

        $this->assertSame(ProviderOutcome::Redirect, $known->outcome);
        $this->assertSame('https://sledovanitv.cz/playlist.m3u', $known->url);
        $this->assertSame(['partnerid' => '110009'], $this->http->lastQuery());
        $this->assertSame(ProviderOutcome::UnknownCustomer, $unknown->outcome);
    }

    public function testAnErrorTheCustomerCannotHelpIsUnavailable(): void
    {
        $this->http->answerJson(200, ['status' => 0, 'error' => 'login used']);

        $answer = $this->provider->signIn($this->customer, '192.0.2.10');

        $this->assertSame(ProviderOutcome::Unavailable, $answer->outcome);
        $this->assertStringContainsString('login used', $answer->reason);
    }

    public function testRefusedPartnerCredentialsAreReportedAsSuch(): void
    {
        $this->http->answer(403, 'Forbidden');

        $answer = $this->provider->signIn($this->customer, '192.0.2.10');

        $this->assertSame(ProviderOutcome::Unavailable, $answer->outcome);
        $this->assertStringContainsString('check the partner login', $answer->reason);
    }

    public function testNoAnswerIsUnavailable(): void
    {
        $this->http->fail('Operation timed out');

        $answer = $this->provider->playlist($this->customer);

        $this->assertSame(ProviderOutcome::Unavailable, $answer->outcome);
        $this->assertStringContainsString('timed out', $answer->reason);
    }
}
