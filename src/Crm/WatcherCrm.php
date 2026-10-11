<?php
declare(strict_types=1);

namespace WatcherTvGateway\Crm;

use WatcherTvGateway\Customer;
use WatcherTvGateway\Environment;
use WatcherTvGateway\Http\HttpClient;
use WatcherTvGateway\Http\HttpError;
use WatcherTvGateway\Http\HttpRequest;

/**
 * Watcher CRM, asked over its customer portal API as a user with the customer-identification role.
 *
 * Only a customer with an installation address counts as connected: the TV service is for the
 * place a connection is installed at, and an address of somebody without one is not a home.
 */
final readonly class WatcherCrm implements CustomerDirectory
{
    public function __construct(
        private HttpClient $http,
        private string $apiUrl,
        private string $apiKey,
    ) {
    }

    /**
     * Set up from CRM_API_URL and CRM_API_KEY.
     */
    public static function fromEnvironment(Environment $env, HttpClient $http): self
    {
        return new self($http, rtrim($env->required('CRM_API_URL'), '/'), $env->required('CRM_API_KEY'));
    }

    public function findByAddress(string $ip): ?Customer
    {
        try {
            $response = $this->http->send(new HttpRequest(
                'GET',
                $this->apiUrl . '/identify?' . http_build_query(['ip' => $ip]),
                ['Authorization' => 'Bearer ' . $this->apiKey, 'Accept' => 'application/json'],
            ));
        } catch (HttpError $error) {
            throw new CrmUnavailable('Watcher CRM did not answer. ' . $error->getMessage(), 0, $error);
        }

        $answer = $response->status === 200 ? $response->json() : null;
        if ($answer === null || !array_key_exists('customer', $answer)) {
            // a refused key is answered with a redirect to the sign-in page, not with 401
            throw new CrmUnavailable(sprintf(
                'Watcher CRM answered with HTTP %d%s.',
                $response->status,
                match (true) {
                    in_array($response->status, [302, 401, 403], true) => ', check CRM_API_KEY and its role',
                    $response->status === 429 => ', too many lookups in a minute',
                    default => '',
                },
            ));
        }

        $customer = $answer['customer'];
        if (!is_array($customer) || !is_array($customer['address'] ?? null)) {
            return null;
        }
        $address = $customer['address'];

        $number = self::text($customer['number'] ?? null);
        if ($number === null) {
            throw new CrmUnavailable('Watcher CRM answered with a customer without a number.');
        }

        return new Customer(
            number: $number,
            title: self::text($customer['title'] ?? null),
            firstName: self::text($customer['first_name'] ?? null),
            lastName: self::text($customer['last_name'] ?? null),
            suffix: self::text($customer['suffix'] ?? null),
            company: self::text($customer['company'] ?? null),
            email: self::text($customer['email'] ?? null),
            street: self::text($address['street'] ?? null),
            houseNumber: self::text($address['number'] ?? null),
            city: self::text($address['city'] ?? null),
            postcode: self::text($address['zip'] ?? null),
        );
    }

    /**
     * A value as text, or null when there is none.
     */
    private static function text(mixed $value): ?string
    {
        if (!is_scalar($value)) {
            return null;
        }
        $text = trim((string)$value);

        return $text === '' ? null : $text;
    }
}
