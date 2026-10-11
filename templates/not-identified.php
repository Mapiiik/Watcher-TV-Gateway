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
    <p><?= $e($t('Dear customer,')) ?></p>
    <p><?= $e($t('you are trying to register with %s. Unfortunately we could not identify you. To register, or to be signed in to an account you already have, you have to be connected to the internet at the connection %s provides you with.', $v['service'], $v['operator'])) ?></p>
    <p><?= $e($t('Your IP address is: %s', $v['ip'])) ?></p>
    <p><?= $e($t('If you are a customer of %s, please call us on %s.', $v['operator'], $v['supportPhone'])) ?></p>
</body>
</html>
