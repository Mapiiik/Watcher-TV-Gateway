<?php
declare(strict_types=1);

namespace WatcherTvGateway;

use Gettext\Translator;
use Gettext\TranslatorInterface;

/**
 * The pages and playlists the gateway answers with when it cannot send the customer on.
 *
 * A template sees its values as `$v`, translates a text with `$t` (sprintf placeholders filled
 * from the further arguments) and escapes for HTML with `$e`. `$v['contact']` is how to reach the
 * operator, in words, and empty when it gave neither a phone nor an e-mail.
 */
final readonly class Templates
{
    private const string BUILT_IN = __DIR__ . '/../templates';

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
        // a directory of the operator's own may be older than the template
        if (!is_file($file)) {
            $file = self::BUILT_IN . '/' . $name . '.php';
        }
        $v = $values + $this->common;
        $translator = $this->translator;
        $t = static fn(string $text, string ...$args): string => vsprintf($translator->gettext($text), $args);
        $e = static fn(string $text): string => htmlspecialchars($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $v['contact'] = self::contact($v['supportPhone'] ?? '', $v['supportEmail'] ?? '', $t);

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

    /**
     * How to reach the operator, with whichever of the two it gave.
     *
     * @param string $phone The support phone.
     * @param string $email The support e-mail.
     * @param \Closure(string, string...): string $t Translates a text.
     */
    private static function contact(string $phone, string $email, \Closure $t): string
    {
        return match (true) {
            $phone !== '' && $email !== '' => $t('call us on %s or write to us at %s', $phone, $email),
            $phone !== '' => $t('call us on %s', $phone),
            $email !== '' => $t('write to us at %s', $email),
            default => '',
        };
    }
}
