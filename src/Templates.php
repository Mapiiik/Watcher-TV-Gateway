<?php
declare(strict_types=1);

namespace WatcherTvGateway;

/**
 * The pages and playlists the gateway answers with when it cannot send the customer on.
 *
 * A template sees its values as `$v` and escapes them for HTML with `$e`.
 */
final readonly class Templates
{
    /**
     * @param string $directory Where the templates are.
     * @param array<string, string> $common Values every template gets: the operator, its support phone, the service.
     */
    public function __construct(
        private string $directory,
        private array $common,
    ) {
    }

    /**
     * An HTML page.
     *
     * @param array<string, string> $values
     */
    public function page(int $status, string $name, array $values): Response
    {
        return new Response($status, ['Content-Type' => 'text/html; charset=UTF-8'], $this->render($name, $values));
    }

    /**
     * An M3U playlist with one entry saying what went wrong, which a player shows as a channel.
     *
     * @param array<string, string> $values
     */
    public function playlist(string $name, array $values): Response
    {
        return new Response(200, ['Content-Type' => 'audio/x-mpegurl; charset=UTF-8'], $this->render($name, $values));
    }

    /**
     * @param array<string, string> $values
     */
    public function render(string $name, array $values): string
    {
        $file = $this->directory . '/' . $name . '.php';
        $v = $values + $this->common;
        $e = static fn(string $text): string => htmlspecialchars($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        ob_start();
        try {
            (static function (string $file, array $v, \Closure $e): void {
                require $file;
            })($file, $v, $e);
        } finally {
            $output = (string)ob_get_clean();
        }

        return $output;
    }
}
