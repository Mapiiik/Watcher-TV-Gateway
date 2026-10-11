<?php
declare(strict_types=1);

namespace WatcherTvGateway;

/**
 * The settings the gateway is given in its environment.
 */
final readonly class Environment
{
    /**
     * @param array<string, string> $values Variable names and their values.
     */
    public function __construct(private array $values)
    {
    }

    /**
     * The environment of this process.
     */
    public static function fromProcess(): self
    {
        return new self(getenv());
    }

    /**
     * A setting that has to be there.
     *
     * @throws \WatcherTvGateway\ConfigurationError When it is not set.
     */
    public function required(string $name): string
    {
        $value = $this->optional($name);
        if ($value === '') {
            throw new ConfigurationError(sprintf('%s is not set.', $name));
        }

        return $value;
    }

    /**
     * A setting that may be left out.
     */
    public function optional(string $name, string $default = ''): string
    {
        $value = trim($this->values[$name] ?? '');

        return $value === '' ? $default : $value;
    }

    /**
     * A setting that is either on or off.
     *
     * @throws \WatcherTvGateway\ConfigurationError When it is neither.
     */
    public function flag(string $name, bool $default): bool
    {
        $value = $this->optional($name);
        if ($value === '') {
            return $default;
        }

        $flag = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        if ($flag === null) {
            throw new ConfigurationError(sprintf('%s must be true or false.', $name));
        }

        return $flag;
    }

    /**
     * A setting that lists values separated by commas.
     *
     * @return list<string>
     */
    public function list(string $name): array
    {
        $items = array_map(trim(...), explode(',', $this->optional($name)));

        return array_values(array_filter($items, static fn(string $item): bool => $item !== ''));
    }
}
