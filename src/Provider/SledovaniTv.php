<?php
declare(strict_types=1);

namespace WatcherTvGateway\Provider;

use WatcherTvGateway\Customer;
use WatcherTvGateway\Environment;
use WatcherTvGateway\Http\HttpClient;
use WatcherTvGateway\Http\HttpError;
use WatcherTvGateway\Http\HttpRequest;

/**
 * SledovaniTV (the ModernTV OTT platform), through its partner API.
 *
 * The partner signs in with HTTP Basic, as the API recommends, so the password never travels
 * in an address. Every answer is a JSON object with `status`, and `error` when it failed.
 *
 * @link https://redoc.moderntv.app/?url=https://sledovanitv.cz/partner/api/_/apidoc
 */
final readonly class SledovaniTv implements TvProvider
{
    public const string DEFAULT_API_URL = 'https://sledovanitv.cz/partner/api';

    public function __construct(
        private HttpClient $http,
        private string $apiUrl,
        private string $partner,
        private string $password,
    ) {
    }

    /**
     * Set up from SLEDOVANITV_PARTNER, SLEDOVANITV_PASSWORD and, optionally, SLEDOVANITV_API_URL.
     */
    public static function fromEnvironment(Environment $env, HttpClient $http): self
    {
        return new self(
            $http,
            rtrim($env->optional('SLEDOVANITV_API_URL', self::DEFAULT_API_URL), '/'),
            $env->required('SLEDOVANITV_PARTNER'),
            $env->required('SLEDOVANITV_PASSWORD'),
        );
    }

    public function name(): string
    {
        return 'SledovaniTV';
    }

    /**
     * `identify-user`: a registered customer is signed in and sent to the stream, a new one to
     * the registration with what we know about them filled in.
     */
    public function signIn(Customer $customer, string $ip): ProviderAnswer
    {
        return $this->call('identify-user', [
            'partnerid' => $customer->number,
            'email' => $customer->email,
            'fullName' => $customer->fullName(),
            'address' => $customer->streetAddress(),
            'city' => $customer->city,
            'postcode' => $customer->postcode,
            // the API takes the customer's address in the form a.b.c.d only
            'ip' => filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false ? null : $ip,
        ]);
    }

    /**
     * `vlc-playlist`: the address of the customer's M3U playlist.
     */
    public function playlist(Customer $customer): ProviderAnswer
    {
        return $this->call('vlc-playlist', ['partnerid' => $customer->number]);
    }

    /**
     * Calls a function of the API and reads its answer.
     *
     * @param array<string, string|null> $parameters Empty ones are left out.
     */
    private function call(string $function, array $parameters): ProviderAnswer
    {
        $query = array_filter($parameters, static fn(?string $value): bool => ($value ?? '') !== '');

        try {
            $response = $this->http->send(new HttpRequest(
                'GET',
                $this->apiUrl . '/' . $function . '?' . http_build_query($query),
                [
                    'Authorization' => 'Basic ' . base64_encode($this->partner . ':' . $this->password),
                    'Accept' => 'application/json',
                ],
            ));
        } catch (HttpError $error) {
            return ProviderAnswer::unavailable(sprintf('SledovaniTV %s did not answer. %s', $function, $error->getMessage()));
        }

        $answer = $response->json();
        if ($answer === null) {
            return ProviderAnswer::unavailable(sprintf(
                'SledovaniTV %s answered with HTTP %d and no JSON%s.',
                $function,
                $response->status,
                in_array($response->status, [401, 403], true) ? ', check the partner login' : '',
            ));
        }

        $error = $answer['error'] ?? null;
        if ($error === 'unknown user') {
            return ProviderAnswer::unknownCustomer();
        }
        if ($error !== null) {
            return ProviderAnswer::unavailable(sprintf(
                'SledovaniTV %s refused with HTTP %d: %s',
                $function,
                $response->status,
                is_scalar($error) ? (string)$error : json_encode($error),
            ));
        }

        $url = $answer['url'] ?? null;
        if ($response->status !== 200 || !is_string($url) || $url === '') {
            return ProviderAnswer::unavailable(sprintf('SledovaniTV %s answered with HTTP %d and no address.', $function, $response->status));
        }

        return ProviderAnswer::redirect($url);
    }
}
