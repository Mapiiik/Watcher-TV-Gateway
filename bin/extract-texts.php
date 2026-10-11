<?php
/**
 * Collects the texts the templates translate with $t() into resources/locales/default.pot and
 * brings every catalogue in resources/locales/<locale>/default.po up to date with it: translations
 * of texts still in use stay, texts no longer in use go, new ones come in untranslated and are
 * listed. Translate them in Poedit, or any editor of .po files.
 *
 * Usage: php bin/extract-texts.php
 */
$root = dirname(__DIR__);

$texts = [];
foreach (glob($root . '/templates/*.php') as $file) {
    $tokens = token_get_all(file_get_contents($file));
    foreach ($tokens as $i => $token) {
        if (!is_array($token) || $token[0] !== T_VARIABLE || $token[1] !== '$t') {
            continue;
        }
        $j = $i + 1;
        while (is_array($tokens[$j]) && $tokens[$j][0] === T_WHITESPACE) {
            $j++;
        }
        if ($tokens[$j] !== '(') {
            continue;
        }
        do {
            $j++;
        } while (is_array($tokens[$j]) && $tokens[$j][0] === T_WHITESPACE);
        if (is_array($tokens[$j]) && $tokens[$j][0] === T_CONSTANT_ENCAPSED_STRING) {
            $texts[stripcslashes(substr($tokens[$j][1], 1, -1))] = true;
        } else {
            fwrite(STDERR, 'Not a plain text: ' . $file . ':' . $token[2] . PHP_EOL);
        }
    }
}
$texts = array_keys($texts);
sort($texts);

$quote = fn(string $text): string => '"' . addcslashes($text, "\"\\\n") . '"';
$header = fn(string $locale): string => implode("\n", array_filter([
    'msgid ""',
    'msgstr ""',
    '"Project-Id-Version: Watcher TV Gateway\n"',
    '"MIME-Version: 1.0\n"',
    '"Content-Type: text/plain; charset=UTF-8\n"',
    '"Content-Transfer-Encoding: 8bit\n"',
    $locale === '' ? null : '"Language: ' . $locale . '\n"',
])) . "\n\n";
$read = function (string $file): array {
    $entries = [];
    $join = fn(string $lines): string => stripcslashes(implode('', array_map(
        fn(string $line): string => substr(trim($line), 1, -1),
        preg_split('/\n(?=")/', trim($lines)),
    )));
    preg_match_all('/^msgid ((?:"(?:\\\\.|[^"\\\\])*"\n?)+)msgstr ((?:"(?:\\\\.|[^"\\\\])*"\n?)+)/m', file_get_contents($file), $matches, PREG_SET_ORDER);
    foreach ($matches as $match) {
        $entries[$join($match[1])] = $join($match[2]);
    }

    return $entries;
};

@mkdir($root . '/resources/locales', 0777, true);
$pot = $header('');
foreach ($texts as $text) {
    $pot .= 'msgid ' . $quote($text) . "\nmsgstr \"\"\n\n";
}
file_put_contents($root . '/resources/locales/default.pot', $pot);
echo count($texts) . " texts in default.pot\n";

foreach (glob($root . '/resources/locales/*/default.po') as $file) {
    $locale = basename(dirname($file));
    $translations = $read($file);
    $po = $header($locale);
    $untranslated = 0;
    foreach ($texts as $text) {
        $translation = $translations[$text] ?? '';
        if ($translation === '') {
            $untranslated++;
            echo "$locale: untranslated: $text\n";
        }
        $po .= 'msgid ' . $quote($text) . "\nmsgstr " . $quote($translation) . "\n\n";
    }
    file_put_contents($file, $po);
    echo "$locale: " . (count($texts) - $untranslated) . ' of ' . count($texts) . " translated\n";
}
