<?php

use rdx\pathe\ScheduleService;
use rdx\pathe\Showing;

require __DIR__ . '/inc.bootstrap.php';

$title = 'Stats';
include 'tpl.header.php';

# bar 2

$numFetches = $db->select_one('fetches', 'count(1)', '1');
$dates = $db->fetch('select min(date) first, max(date) last from fetches')->first();
$numMovies = $db->select_one('movies', 'count(1)', '1');
$numShowings = $db->select_one('showings', 'count(1)', '1');
$rawFlags = $db->select_fields('showings', 'flags, count(1) num', "1 GROUP BY flags");

$flags = [];
foreach ($rawFlags as $flag => $num) {
	$flag = preg_replace('#\bnacht (\d+[ -](?:\w+ )?op \d+[ -]\w+)#', 'nacht X op Y', $flag);
	isset($flags[$flag]) or $flags[$flag] = 0;
	$flags[$flag] += $num;
}
arsort($flags, SORT_NUMERIC);

?>

<dl>
	<dt>Fetches</dt>
	<dd><?= number_format($numFetches, 0, '.', ' ') ?></dd>

	<dt>Date range</dt>
	<dd><?= $dates->first ?> - <?= $dates->last ?></dd>

	<dt>Movies</dt>
	<dd><?= number_format($numMovies, 0, '.', ' ') ?></dd>

	<dt>Showings</dt>
	<dd><?= number_format($numShowings, 0, '.', ' ') ?></dd>

	<dt>Showing flags</dt>
	<dd><? foreach ($flags as $flag => $used): ?><?= $flag ?: '&lt;none&gt;' ?>: <?= $used ?><br><? endforeach ?></dd>
</dl>
