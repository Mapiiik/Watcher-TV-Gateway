<?php
declare(strict_types=1);

namespace WatcherTvGateway\Test;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use WatcherTvGateway\ConfigurationError;
use WatcherTvGateway\Customer;
use WatcherTvGateway\Environment;
use WatcherTvGateway\Gateway;
use WatcherTvGateway\Provider\ProviderAnswer;
use WatcherTvGateway\Response;
use WatcherTvGateway\Templates;
use WatcherTvGateway\Test\Support\FakeDirectory;
use WatcherTvGateway\Test\Support\FakeProvider;

#[CoversClass(Gateway::class)]
#[CoversClass(Templates::class)]
#[CoversClass(Response::class)]
final class GatewayTest extends TestCase
{
    private const string IP = '192.0.2.10';

    public function testARecognisedCustomerIsSentOnToSignIn(): void
    {
        $provider = new FakeProvider(ProviderAnswer::redirect('https://tv.example/login?token=x'));

        $response = $this->gateway($provider)->handle('GET', '/', self::IP);

        $this->assertSame(303, $response->status);
        $this->assertSame('https://tv.example/login?token=x', $response->headers['Location']);
        $this->assertSame(['110009@' . self::IP], $provider->signedIn);
    }

    public function testSomebodyWhoIsNotACustomerIsToldWhereTheyCameFrom(): void
    {
        $provider = new FakeProvider(ProviderAnswer::redirect('https://tv.example/'));

        $response = $this->gateway($provider)->handle('GET', '/', '198.51.100.7');

        $this->assertSame(403, $response->status);
        $this->assertStringContainsString('TestTV – identifikace selhala', $response->body);
        $this->assertStringContainsString('198.51.100.7', $response->body);
        $this->assertStringContainsString('NETAIR, s.r.o.', $response->body);
        $this->assertStringContainsString('+420 604 553 444', $response->body);
        $this->assertSame([], $provider->signedIn);
    }

    public function testACustomerOfAnExcludedCompanyIsNotSentOn(): void
    {
        $provider = new FakeProvider(ProviderAnswer::redirect('https://tv.example/'));
        $directory = new FakeDirectory([self::IP => new Customer('110009', company: 'MOPOS Communications')]);

        $response = $this->gateway($provider, $directory)->handle('GET', '/', self::IP);

        $this->assertSame(403, $response->status);
        $this->assertSame([], $provider->signedIn);
    }

    public function testWhenTheCrmOrTheProviderFailsTheServiceIsUnavailable(): void
    {
        $unavailableCrm = $this->gateway(
            new FakeProvider(ProviderAnswer::redirect('https://tv.example/')),
            new FakeDirectory(unavailable: true),
        )->handle('GET', '/', self::IP);
        $unavailableProvider = $this->gateway(new FakeProvider(ProviderAnswer::unavailable('test')))
            ->handle('GET', '/', self::IP);

        foreach ([$unavailableCrm, $unavailableProvider] as $response) {
            $this->assertSame(503, $response->status);
            $this->assertStringContainsString('mimo provoz', $response->body);
        }
    }

    public function testThePlaylistOfARecognisedCustomerIsTheProviders(): void
    {
        $response = $this->gateway(new FakeProvider(ProviderAnswer::redirect('https://tv.example/list.m3u')))
            ->handle('GET', '/channels.m3u', self::IP);

        $this->assertSame(302, $response->status);
        $this->assertSame('https://tv.example/list.m3u', $response->headers['Location']);
    }

    public function testAPlayerIsToldInThePlaylistWhyThereAreNoChannels(): void
    {
        $notIdentified = $this->gateway(new FakeProvider(ProviderAnswer::redirect('x')))
            ->handle('GET', '/channels.m3u', '198.51.100.7');
        $notRegistered = $this->gateway(new FakeProvider(ProviderAnswer::unknownCustomer()))
            ->handle('GET', '/channels.m3u', self::IP);
        $unavailable = $this->gateway(new FakeProvider(ProviderAnswer::unavailable('test')))
            ->handle('GET', '/channels.m3u', self::IP);

        $this->assertSame("#EXTM3U\n#EXTINF:,Vaše adresa 198.51.100.7 nemá vysílání zaregistrované\nnot-identified.jpg\n", $notIdentified->body);
        $this->assertSame('audio/x-mpegurl; charset=UTF-8', $notIdentified->headers['Content-Type']);
        $this->assertStringEndsWith("not-registered.jpg\n", $notRegistered->body);
        $this->assertStringContainsString('Seznam kanálů se nepodařilo stáhnout', $unavailable->body);
    }

    public function testSomethingThatIsNotAnAddressIsNotAskedAbout(): void
    {
        $directory = new FakeDirectory();

        $response = $this->gateway(new FakeProvider(ProviderAnswer::redirect('x')), $directory)
            ->handle('GET', '/', '<script>');

        $this->assertSame(403, $response->status);
        $this->assertSame([], $directory->asked);
        $this->assertStringContainsString('&lt;script&gt;', $response->body);
    }

    public function testOnlyReadingIsAnsweredAndOnlyTheTwoAddresses(): void
    {
        $gateway = $this->gateway(new FakeProvider(ProviderAnswer::redirect('x')));

        $this->assertSame(405, $gateway->handle('POST', '/', self::IP)->status);
        $this->assertSame(404, $gateway->handle('GET', '/wp-login.php', self::IP)->status);
        $this->assertSame(303, $gateway->handle('HEAD', '/', self::IP)->status);
    }

    public function testTheGatewayIsSetUpFromTheEnvironment(): void
    {
        $gateway = Gateway::fromEnvironment(new Environment([
            'CRM_API_URL' => 'https://crm.example.net/api/customer-portal',
            'CRM_API_KEY' => 'portal-key',
            'TV_PROVIDER' => 'sledovanitv',
            'SLEDOVANITV_PARTNER' => 'netair',
            'SLEDOVANITV_PASSWORD' => 'secret',
            'OPERATOR_NAME' => 'NETAIR, s.r.o.',
            'SUPPORT_PHONE' => '+420 604 553 444',
        ]));

        $this->assertSame(404, $gateway->handle('GET', '/nothing', self::IP)->status);

        $this->expectException(ConfigurationError::class);
        Gateway::fromEnvironment(new Environment(['TV_PROVIDER' => 'sledovanitv']));
    }

    public function testTemplatesOfItsOwnHaveToBeThere(): void
    {
        $this->expectException(ConfigurationError::class);
        $this->expectExceptionMessage('TEMPLATES_DIR /nowhere is not a directory.');

        Gateway::fromEnvironment(new Environment([
            'CRM_API_URL' => 'https://crm.example.net/api/customer-portal',
            'CRM_API_KEY' => 'portal-key',
            'TV_PROVIDER' => 'sledovanitv',
            'SLEDOVANITV_PARTNER' => 'netair',
            'SLEDOVANITV_PASSWORD' => 'secret',
            'OPERATOR_NAME' => 'NETAIR, s.r.o.',
            'SUPPORT_PHONE' => '+420 604 553 444',
            'TEMPLATES_DIR' => '/nowhere',
        ]));
    }

    private function gateway(FakeProvider $provider, ?FakeDirectory $directory = null): Gateway
    {
        return new Gateway(
            $directory ?? new FakeDirectory([self::IP => new Customer('110009', company: 'NETAIR, s.r.o.')]),
            $provider,
            new Templates(dirname(__DIR__) . '/templates', [
                'operator' => 'NETAIR, s.r.o.',
                'supportPhone' => '+420 604 553 444',
                'service' => $provider->name(),
            ]),
            ['MOPOS'],
        );
    }
}
