<?php
/**
 * A player shows the entry as a channel, the picture next to the playlist explains it.
 *
 * @var array<string, string> $v
 */
?>#EXTM3U
#EXTINF:,Vaše adresa <?= preg_replace('/[\r\n]/', '', $v['ip']) ?> nemá vysílání zaregistrované
not-identified.jpg
