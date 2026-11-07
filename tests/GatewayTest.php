<?php
declare(strict_types=1);

namespace WatcherTvGateway\Test;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use WatcherTvGateway\ConfigurationError;
use WatcherTvGateway\Customer;
use WatcherTvGateway\CustomerNetworks;
use WatcherTvGateway\Environment;
use WatcherTvGateway\Gateway;
use WatcherTvGateway\Provider\ProviderAnswer;
use WatcherTvGateway\Response;
use WatcherTvGateway\Templates;
use WatcherTvGateway\Test\Support\FakeDirectory;
use WatcherTvGateway\Test\Support\FakeProvider;
use WatcherTvGateway\Translations;

#[CoversClass(Gateway::class)]
#[CoversClass(Templates::class)]
#[CoversClass(Response::class)]
#[CoversClass(Translations::class)]
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
        $this->assertStringContainsString('TestTV – identification failed', $response->body);
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
            $this->assertStringContainsString('out of service', $response->body);
        }
    }

    public function testACustomerTheProviderRefusesIsToldToCallRatherThanToTryAgain(): void
    {
        $response = $this->gateway(new FakeProvider(ProviderAnswer::refused('test')))
            ->handle('GET', '/', self::IP);

        $this->assertSame(403, $response->status);
        $this->assertStringContainsString('call us on +420 604 553 444', $response->body);
        $this->assertStringContainsString(self::IP, $response->body);
        $this->assertStringNotContainsString('try again in a while', $response->body);
    }

    /**
     * Each page offers whichever of the phone and the e-mail the operator gave.
     */
    #[TestWith(['+420 604 553 444', 'support@example.net', 'call us on +420 604 553 444 or write to us at support@example.net'])]
    #[TestWith(['+420 604 553 444', '', 'call us on +420 604 553 444'])]
    #[TestWith(['', 'support@example.net', 'write to us at support@example.net'])]
    public function testThePagesOfferWhatTheOperatorGave(string $phone, string $email, string $offered): void
    {
        $templates = $this->pagesWith($phone, $email);

        $this->assertStringContainsString('Please ' . $offered . ' and we will', $templates->render('refused', ['ip' => self::IP]));
        $this->assertStringContainsString('please ' . $offered . '.', $templates->render('unavailable', ['ip' => self::IP]));
        $this->assertStringContainsString('please ' . $offered . '.', $templates->render('not-identified', ['ip' => self::IP]));
    }

    /**
     * With neither there is nothing to offer, and no sentence is left standing without an end.
     */
    public function testWithoutAContactThePagesSayNothingAboutIt(): void
    {
        $templates = $this->pagesWith('', '');

        $this->assertStringNotContainsString('sort it out', $templates->render('refused', ['ip' => self::IP]));
        $this->assertStringNotContainsString('If the trouble persists', $templates->render('unavailable', ['ip' => self::IP]));
        $this->assertStringNotContainsString('If you are a customer', $templates->render('not-identified', ['ip' => self::IP]));
    }

    private function pagesWith(string $phone, string $email): Templates
    {
        return new Templates(dirname(__DIR__) . '/templates', [
            'operator' => 'NETAIR, s.r.o.',
            'supportPhone' => $phone,
            'supportEmail' => $email,
            'service' => 'TestTV',
            'language' => 'en',
        ]);
    }

    public function testAPageTheOperatorsOwnDirectoryLacksIsTheBuiltInOne(): void
    {
        $templates = new Templates(__DIR__, [
            'supportPhone' => '+420 604 553 444',
            'service' => 'TestTV',
            'language' => 'en',
        ]);

        $this->assertStringContainsString('call us on', $templates->page(403, 'refused', ['ip' => self::IP])->body);
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
        $refused = $this->gateway(new FakeProvider(ProviderAnswer::refused('test')))
            ->handle('GET', '/channels.m3u', self::IP);

        $this->assertSame("#EXTM3U\n#EXTINF:,Your address 198.51.100.7 has no TV registered\nimages/not-identified.jpg\n", $notIdentified->body);
        $this->assertSame('audio/x-mpegurl; charset=UTF-8', $notIdentified->headers['Content-Type']);
        $this->assertStringEndsWith("images/not-registered.jpg\n", $notRegistered->body);
        $this->assertStringContainsString('The channel list could not be downloaded', $unavailable->body);
        $this->assertStringContainsString('The channel list could not be downloaded', $refused->body);
    }

    public function testTheTextsAndPicturesAreInTheLanguageOfTheDeployment(): void
    {
        $env = new Environment([
            'CRM_API_URL' => 'https://crm.example.net/api/customer-portal',
            'CRM_API_KEY' => 'portal-key',
            'TV_PROVIDER' => 'sledovanitv',
            'SLEDOVANITV_PARTNER' => 'netair',
            'SLEDOVANITV_PASSWORD' => 'secret',
            'OPERATOR_NAME' => 'NETAIR, s.r.o.',
            'SUPPORT_PHONE' => '+420 604 553 444',
            'APP_DEFAULT_LOCALE' => 'cs_CZ',
        ]);
        $translator = Translations::forLocale('cs_CZ', dirname(__DIR__) . '/resources/locales');
        $templates = new Templates(dirname(__DIR__) . '/templates', [
            'operator' => 'NETAIR, s.r.o.',
            'supportPhone' => '+420 604 553 444',
            'service' => 'SledovaniTV',
            'language' => 'cs',
            'images' => 'images/cs/',
        ], $translator);

        $page = $templates->page(403, 'not-identified', ['ip' => '198.51.100.7']);
        $playlist = $templates->playlist('playlist-not-identified', ['ip' => '198.51.100.7']);

        $this->assertStringContainsString('<html lang="cs">', $page->body);
        $this->assertStringContainsString('SledovaniTV – identifikace selhala', $page->body);
        $this->assertStringContainsString('kterou Vám NETAIR, s.r.o. poskytuje.', $page->body);
        $this->assertSame("#EXTM3U\n#EXTINF:,Vaše adresa 198.51.100.7 nemá vysílání zaregistrované\nimages/cs/not-identified.jpg\n", $playlist->body);
        $this->assertFileExists(dirname(__DIR__) . '/public/images/cs/not-identified.jpg');
        $this->assertSame(404, Gateway::fromEnvironment($env)->handle('GET', '/nothing', self::IP)->status);
    }

    public function testALocaleWithoutACatalogueStaysEnglish(): void
    {
        $translator = Translations::forLocale('de_DE', dirname(__DIR__) . '/resources/locales');

        $this->assertSame('Dear customer,', $translator->gettext('Dear customer,'));
        $this->assertSame('cs', Translations::language('cs_CZ'));
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

    public function testAVisitorFromOutsideTheCustomerNetworksCostsTheCrmNothing(): void
    {
        $directory = new FakeDirectory();
        $gateway = $this->gateway(
            new FakeProvider(ProviderAnswer::redirect('https://tv.example/')),
            $directory,
            new CustomerNetworks(['192.0.2.0/24']),
        );

        $page = $gateway->handle('GET', '/', '198.51.100.7');
        $playlist = $gateway->handle('GET', '/channels.m3u', '198.51.100.7');

        $this->assertSame(403, $page->status);
        $this->assertStringContainsString('198.51.100.7', $page->body);
        $this->assertStringContainsString('198.51.100.7', $playlist->body);
        $this->assertSame([], $directory->asked);
    }

    public function testACustomerInsideTheCustomerNetworksIsRecognised(): void
    {
        $provider = new FakeProvider(ProviderAnswer::redirect('https://tv.example/login?token=x'));
        $directory = new FakeDirectory([self::IP => new Customer('110009')]);

        $response = $this->gateway($provider, $directory, new CustomerNetworks(['192.0.2.0/24']))
            ->handle('GET', '/', self::IP);

        $this->assertSame(303, $response->status);
        $this->assertSame([self::IP], $directory->asked);
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

    public function testTheContactsAreLeftToTheOperator(): void
    {
        $gateway = Gateway::fromEnvironment(new Environment([
            'CRM_API_URL' => 'https://crm.example.net/api/customer-portal',
            'CRM_API_KEY' => 'portal-key',
            'TV_PROVIDER' => 'sledovanitv',
            'SLEDOVANITV_PARTNER' => 'netair',
            'SLEDOVANITV_PASSWORD' => 'secret',
            'OPERATOR_NAME' => 'NETAIR, s.r.o.',
        ]));

        $this->assertSame(404, $gateway->handle('GET', '/nothing', self::IP)->status);
    }

    public function testTheEmailOfTheSettingsReachesThePages(): void
    {
        $gateway = Gateway::fromEnvironment(new Environment([
            'CRM_API_URL' => 'https://crm.example.net/api/customer-portal',
            'CRM_API_KEY' => 'portal-key',
            'TV_PROVIDER' => 'sledovanitv',
            'SLEDOVANITV_PARTNER' => 'netair',
            'SLEDOVANITV_PASSWORD' => 'secret',
            'OPERATOR_NAME' => 'NETAIR, s.r.o.',
            'SUPPORT_EMAIL' => 'support@example.net',
            'CUSTOMER_NETWORKS' => '10.0.0.0/8 192.0.2.0/24',
        ]));

        $body = $gateway->handle('GET', '/', '198.51.100.7')->body;

        $this->assertStringContainsString('write to us at support@example.net', $body);
    }

    public function testCustomerNetworksThatCannotBeReadAreAMistakeInTheSettings(): void
    {
        $this->expectException(ConfigurationError::class);
        $this->expectExceptionMessage('CUSTOMER_NETWORKS');

        Gateway::fromEnvironment(new Environment([
            'CRM_API_URL' => 'https://crm.example/api/customer-portal',
            'CRM_API_KEY' => 'key',
            'TV_PROVIDER' => 'sledovanitv',
            'SLEDOVANITV_PARTNER' => 'partner',
            'SLEDOVANITV_PASSWORD' => 'secret',
            'OPERATOR_NAME' => 'NETAIR, s.r.o.',
            'SUPPORT_PHONE' => '+420 604 553 444',
            'CUSTOMER_NETWORKS' => '192.0.2.0/24, nonsense',
        ]));
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

    private function gateway(
        FakeProvider $provider,
        ?FakeDirectory $directory = null,
        CustomerNetworks $networks = new CustomerNetworks(),
    ): Gateway {
        return new Gateway(
            $directory ?? new FakeDirectory([self::IP => new Customer('110009', company: 'NETAIR, s.r.o.')]),
            $provider,
            new Templates(dirname(__DIR__) . '/templates', [
                'operator' => 'NETAIR, s.r.o.',
                'supportPhone' => '+420 604 553 444',
                'service' => $provider->name(),
                'language' => 'en',
                'images' => 'images/',
            ]),
            ['MOPOS'],
            $networks,
        );
    }
}
