<?php
/**
 * A player shows the entry as a channel, the picture next to the playlist explains it.
 *
 * @var array<string, string> $v
 * @var \Closure(string, string...): string $t
 */
?>#EXTM3U
#EXTINF:,<?= preg_replace('/[\r\n]/', '', $t('Your address %s has no TV registered', $v['ip'])) ?>

<?= $v['images'] ?>not-identified.jpg
