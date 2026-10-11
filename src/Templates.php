<?php
declare(strict_types=1);

namespace WatcherTvGateway;

use Gettext\Translator;
use Gettext\TranslatorInterface;

/**
 * The pages and playlists the gateway answers with when it cannot send the customer on.
 *
 * A template sees its values as `$v`, translates a text with `$t` (sprintf placeholders filled
 * from the further arguments) and escapes for HTML with `$e`.
 */
final readonly class Templates
{
    private TranslatorInterface $translator;

    /**
     * @param string $directory Where the templates are.
     * @param array<string, string> $common Values every template gets: the operator, its support phone, the service, where the pictures are.
     * @param \Gettext\TranslatorInterface|null $translator The texts in the deployment's language; none for English.
     */
    public function __construct(
        private string $directory,
        private array $common,
        ?TranslatorInterface $translator = null,
    ) {
        $this->translator = $translator ?? new Translator();
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
        $translator = $this->translator;
        $t = static fn(string $text, string ...$args): string => vsprintf($translator->gettext($text), $args);
        $e = static fn(string $text): string => htmlspecialchars($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        ob_start();
        try {
            (static function (string $file, array $v, \Closure $t, \Closure $e): void {
                require $file;
            })($file, $v, $t, $e);
        } finally {
            $output = (string)ob_get_clean();
        }

        return $output;
    }
}
