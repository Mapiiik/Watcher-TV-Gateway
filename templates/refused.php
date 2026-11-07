<?php
/**
 * The provider will not take the customer's details, so only the operator can help.
 *
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
    <p><?= $e($t('%s cannot sign you in or register you with the details we have for you, and trying again will not change that.', $v['service'])) ?></p>
    <p><?= $e($t('Your IP address is: %s', $v['ip'])) ?></p>
    <p><?= $e($t('Please call us on %s and we will sort it out.', $v['supportPhone'])) ?></p>
</body>
</html>
