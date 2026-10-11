<?php
/**
 * The customer is recognised but has not registered with the service yet.
 *
 * @var array<string, string> $v
 * @var \Closure(string, string...): string $t
 */
?>#EXTM3U
#EXTINF:,<?= preg_replace('/[\r\n]/', '', $t('Your address %s has no TV registered', $v['ip'])) ?>

<?= $v['images'] ?>not-registered.jpg
