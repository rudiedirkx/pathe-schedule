<?php

use rdx\pathe\ScheduleService;
use rdx\pathe\Showing;

require __DIR__ . '/inc.bootstrap.php';

$title = 'Stats';
include 'tpl.header.php';

$numFetches = $db->select_one('fetches', 'count(1)', '1');
$dates = $db->fetch('select min(date) first, max(date) last from fetches')->first();
$numMovies = $db->select_one('movies', 'count(1)', '1');
$numShowings = $db->select_one('showings', 'count(1)', '1');
$flags = $db->select_fields('showings', 'distinct flags', "flags <> '' ORDER BY length(flags)");

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
	<dd><ul><li><?= implode('</li><li>', $flags) ?></li></ul></dd>
</dl>
