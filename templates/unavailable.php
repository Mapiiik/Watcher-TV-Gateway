<?php
/**
 * @var array<string, string> $v
 * @var \Closure(string, string...): string $t
 * @var \Closure(string): string $e
 */
?><!DOCTYPE html>
<html lang="<?= $e($v['language']) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $e($t('%s – identification failed', $v['service'])) ?></title>
</head>
<body>
    <h2><?= $e($t('%s – identification failed', $v['service'])) ?></h2>
    <p><?= $e($t('Signing in and registration are out of service at the moment. Please try again in a while.')) ?></p>
    <p><?= $e($t('Your IP address is: %s', $v['ip'])) ?></p>
    <p><?= $e($t('If the trouble persists, please call us on %s.', $v['supportPhone'])) ?></p>
</body>
</html>
