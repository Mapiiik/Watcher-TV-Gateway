<?php
declare(strict_types=1);

namespace WatcherTvGateway;

use Gettext\Generator\ArrayGenerator;
use Gettext\Loader\PoLoader;
use Gettext\Translator;
use Gettext\TranslatorInterface;

/**
 * The texts of the pages in the language of the deployment, as in Watcher CRM: English in the
 * templates, translated from resources/locales/<locale>/default.po.
 */
final class Translations
{
    /**
     * The translator of a locale, from its catalogue or the one of its language (cs_CZ, then cs).
     * Without either the texts stay English.
     *
     * @param string $locale For example cs_CZ.
     * @param string $directory Where the catalogues are.
     */
    public static function forLocale(string $locale, string $directory): TranslatorInterface
    {
        $translator = new Translator();
        foreach ([$locale, self::language($locale)] as $name) {
            $file = $directory . '/' . basename($name) . '/default.po';
            if (is_file($file)) {
                $translator->addTranslations((new ArrayGenerator())->generateArray((new PoLoader())->loadFile($file)));
                break;
            }
        }

        return $translator;
    }

    /**
     * The language of a locale: cs of cs_CZ.
     */
    public static function language(string $locale): string
    {
        return strtolower(substr($locale, 0, 2));
    }
}
