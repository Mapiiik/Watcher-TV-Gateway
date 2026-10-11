<?php
declare(strict_types=1);

namespace WatcherTvGateway;

/**
 * A customer as the CRM knows them: who they are and where their connection is installed.
 *
 * The customer number is their identity at the TV provider, so a customer who comes back is
 * the same user there.
 */
final readonly class Customer
{
    public function __construct(
        public string $number,
        public ?string $title = null,
        public ?string $firstName = null,
        public ?string $lastName = null,
        public ?string $suffix = null,
        public ?string $company = null,
        public ?string $email = null,
        public ?string $street = null,
        public ?string $houseNumber = null,
        public ?string $city = null,
        public ?string $postcode = null,
    ) {
    }

    /**
     * The customer's name, without the company.
     */
    public function fullName(): string
    {
        return self::join([$this->title, $this->firstName, $this->lastName, $this->suffix]);
    }

    /**
     * Street and house number. A place without streets is addressed by the house number alone.
     */
    public function streetAddress(): string
    {
        if (($this->street ?? '') !== '') {
            return self::join([$this->street, $this->houseNumber]);
        }

        return ($this->houseNumber ?? '') !== '' ? 'č.p. ' . $this->houseNumber : '';
    }

    /**
     * Whether the customer belongs to one of the given companies, by part of its name.
     *
     * @param list<string> $companies Names, or parts of them.
     */
    public function belongsToAnyOf(array $companies): bool
    {
        foreach ($companies as $company) {
            if ($this->company !== null && mb_stripos($this->company, $company) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<?string> $parts
     */
    private static function join(array $parts): string
    {
        return implode(' ', array_filter($parts, static fn(?string $part): bool => ($part ?? '') !== ''));
    }
}
