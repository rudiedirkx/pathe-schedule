<?php

use rdx\pathe\ScheduleService;
use rdx\pathe\Showing;

require __DIR__ . '/inc.bootstrap.php';

$title = 'Stats';
include 'tpl.header.php';

# bar 2

$numFetches = db()->select_one('fetches', 'count(1)', '1');
$dates = db()->fetch_first("select min(date) first, max(date) last from fetches where date > '1970-01-01'");
$numMovies = db()->select_one('movies', 'count(1)', '1');
$numShowings = db()->select_one('showings', 'count(1)', '1');
/** @var array<string, int> */
$rawFlags = db()->fetch("SELECT strftime('%Y', date) year, flags, COUNT(1) num FROM showings GROUP BY year, flags");
// dd($rawFlags);

/** @var array<int, array<string, int>> $flags */
$flagCounts = [];
foreach ($rawFlags as $row) {
	$flag = preg_replace('#\bnacht (\d+[ -](?:\w+ )?op \d+[ -]\w+)#', 'nacht X op Y', strtolower($row->flags ?? ''));
	$flagCounts[$row->year] ??= [];
	$flagCounts[$row->year][$flag] ??= 0;
	$flagCounts[$row->year][$flag] += $row->num;
}
krsort($flagCounts, SORT_NUMERIC);

$flagNames = array_unique(array_merge(...array_values(array_map(fn(array $flags) => array_keys($flags), $flagCounts))));
sort($flagNames);

?>
<style>
	th:first-child {
		text-align: right;
	}
	th + th,
	th ~ td {
		text-align: center;
	}
</style>

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
	<dd>
		<table cellpadding="4" cellspacing="0" border="1">
			<thead>
				<tr>
					<th></th>
					<? foreach (array_keys($flagCounts) as $year): ?>
						<th><?= $year ?></th>
					<? endforeach ?>
				</tr>
			</thead>
			<tbody>
				<? foreach ($flagNames as $flag): ?>
					<tr>
						<th><?= html($flag ?: '<none>') ?></th>
						<? foreach (array_keys($flagCounts) as $year): ?>
							<td><?= $flagCounts[$year][$flag] ?? '' ?></td>
						<? endforeach ?>
					</tr>
				<? endforeach ?>
			</tbody>
		</table>
	</dd>
</dl>
