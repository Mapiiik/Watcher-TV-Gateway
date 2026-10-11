<?php
/**
 * @var array<string, string> $v
 * @var \Closure(string): string $e
 */
?><!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $e($v['service']) ?> – identifikace selhala</title>
</head>
<body>
    <h2><?= $e($v['service']) ?> – identifikace selhala</h2>
    <p>Registrace a přihlašování je teď mimo provoz. Zkuste to prosím za chvíli.</p>
    <p>Vaše IP adresa je: <?= $e($v['ip']) ?></p>
    <p>Pokud potíže trvají, informujte nás prosím na telefonním čísle <?= $e($v['supportPhone']) ?>.</p>
</body>
</html>
