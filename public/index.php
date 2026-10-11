<?php
declare(strict_types=1);

use WatcherTvGateway\ConfigurationError;
use WatcherTvGateway\Environment;
use WatcherTvGateway\Gateway;

require dirname(__DIR__) . '/vendor/autoload.php';

try {
    $gateway = Gateway::fromEnvironment(Environment::fromProcess());
} catch (ConfigurationError $error) {
    error_log('Watcher TV Gateway is not configured: ' . $error->getMessage());
    http_response_code(500);
    exit;
}

$server = static fn(string $name): string => is_string($_SERVER[$name] ?? null) ? $_SERVER[$name] : '';
$path = parse_url($server('REQUEST_URI'), PHP_URL_PATH);

// the client's address comes from the PROXY protocol header, which Caddy takes only from PROXY_ALLOW
$gateway
    ->handle($server('REQUEST_METHOD'), is_string($path) && $path !== '' ? $path : '/', $server('REMOTE_ADDR'))
    ->send();
