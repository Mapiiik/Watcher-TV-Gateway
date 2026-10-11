<?php
declare(strict_types=1);

namespace WatcherTvGateway;

use WatcherTvGateway\Crm\CrmUnavailable;
use WatcherTvGateway\Crm\CustomerDirectory;
use WatcherTvGateway\Crm\WatcherCrm;
use WatcherTvGateway\Http\CurlHttpClient;
use WatcherTvGateway\Provider\ProviderOutcome;
use WatcherTvGateway\Provider\Providers;
use WatcherTvGateway\Provider\TvProvider;

/**
 * Recognises a customer by the address they come from and sends them on to their TV service.
 *
 * `/` signs the customer in, or lets them register, and `/channels.m3u` hands a player their
 * channel playlist. What cannot be done ends on a page, or in a playlist, that says so and shows
 * the address the customer came from, which is what support asks for.
 */
final readonly class Gateway
{
    /**
     * @param list<string> $excludedCompanies Customers of these companies are not offered the service.
     */
    public function __construct(
        private CustomerDirectory $customers,
        private TvProvider $provider,
        private Templates $templates,
        private array $excludedCompanies = [],
    ) {
    }

    /**
     * The gateway as the environment sets it up.
     *
     * @throws \WatcherTvGateway\ConfigurationError When a setting is missing or wrong.
     */
    public static function fromEnvironment(Environment $env): self
    {
        $provider = Providers::fromEnvironment($env, new CurlHttpClient());

        return new self(
            WatcherCrm::fromEnvironment($env, new CurlHttpClient(verifyTls: $env->flag('CRM_API_VERIFY_TLS', true))),
            $provider,
            new Templates(dirname(__DIR__) . '/templates', [
                'operator' => $env->required('OPERATOR_NAME'),
                'supportPhone' => $env->required('SUPPORT_PHONE'),
                'service' => $provider->name(),
            ]),
            $env->list('EXCLUDED_COMPANIES'),
        );
    }

    /**
     * Answers a request.
     *
     * @param string $ip The client's address, as the web server saw it through the proxy.
     */
    public function handle(string $method, string $path, string $ip): Response
    {
        if (!in_array($method, ['GET', 'HEAD'], true)) {
            return new Response(405, ['Allow' => 'GET, HEAD']);
        }

        return match ($path) {
            '/', '/index.php' => $this->signIn($ip),
            '/channels.m3u' => $this->playlist($ip),
            default => Response::text(404, "Not found\n"),
        };
    }

    private function signIn(string $ip): Response
    {
        try {
            $customer = $this->recognise($ip);
        } catch (CrmUnavailable $error) {
            error_log($error->getMessage());

            return $this->templates->page(503, 'unavailable', ['ip' => $ip]);
        }
        if ($customer === null) {
            return $this->templates->page(403, 'not-identified', ['ip' => $ip]);
        }

        $answer = $this->provider->signIn($customer, $ip);
        if ($answer->outcome === ProviderOutcome::Redirect) {
            return Response::redirect($answer->url);
        }
        error_log($answer->reason !== '' ? $answer->reason : $this->provider->name() . ' does not know the customer.');

        return $this->templates->page(503, 'unavailable', ['ip' => $ip]);
    }

    private function playlist(string $ip): Response
    {
        try {
            $customer = $this->recognise($ip);
        } catch (CrmUnavailable $error) {
            error_log($error->getMessage());

            return $this->templates->playlist('playlist-unavailable', ['ip' => $ip]);
        }
        if ($customer === null) {
            return $this->templates->playlist('playlist-not-identified', ['ip' => $ip]);
        }

        $answer = $this->provider->playlist($customer);

        return match ($answer->outcome) {
            ProviderOutcome::Redirect => Response::redirect($answer->url, 302),
            ProviderOutcome::UnknownCustomer => $this->templates->playlist('playlist-not-registered', ['ip' => $ip]),
            ProviderOutcome::Unavailable => $this->unavailablePlaylist($answer->reason, $ip),
        };
    }

    private function unavailablePlaylist(string $reason, string $ip): Response
    {
        error_log($reason);

        return $this->templates->playlist('playlist-unavailable', ['ip' => $ip]);
    }

    /**
     * The customer connected at the address, unless their company is not offered the service.
     *
     * @throws \WatcherTvGateway\Crm\CrmUnavailable
     */
    private function recognise(string $ip): ?Customer
    {
        if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
            return null;
        }
        $customer = $this->customers->findByAddress($ip);

        return $customer === null || $customer->belongsToAnyOf($this->excludedCompanies) ? null : $customer;
    }
}
