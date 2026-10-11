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
    <p>Vážený zákazníku,</p>
    <p>pokoušíte se zaregistrovat ke službě <?= $e($v['service']) ?>. Bohužel se Vás nepodařilo identifikovat. Pro registraci nebo automatické přihlášení do již vytvořeného účtu je nutné, abyste byli připojeni k internetu na přípojce, kterou Vám poskytuje <?= $e($v['operator']) ?></p>
    <p>Vaše IP adresa je: <?= $e($v['ip']) ?></p>
    <p>Pokud jste zákazníkem <?= $e($v['operator']) ?>, informujte nás prosím na telefonním čísle <?= $e($v['supportPhone']) ?>.</p>
</body>
</html>
