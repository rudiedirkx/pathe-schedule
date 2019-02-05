<?php

use rdx\pathe\ScheduleService;
use rdx\pathe\Showing;

require __DIR__ . '/inc.bootstrap.php';

$date = $_GET['date'] ?? 'today';

$service = new ScheduleService('eindhoven', $date);
$date = $service->getDate();

$movies = $service->getSchedule();

$title = $service->getTitle();
include 'tpl.header.php';

?>

<p>
	<a href="./">Today</a> |
	<a href="?date=tomorrow">Tomorrow</a> |
	<a href="?date=2+days">+2</a> |
	<a href="?date=3+days">+3</a>
</p>

<h1><a href="<?= html($service->getScheduleUrl()) ?>"><?= html($service->getTitle()) ?></a></h1>

<? foreach ($movies as $movie): ?>
	<div class="movie <?= $movie->status ?>">
		<h3><?= html($movie->movie) ?> (<?= $movie->movie->pretty_release_date ?>)</h3>
		<ul>
			<? foreach ($movie->showings as $showing): ?>
				<li>
					<?= html($showing->orig_start_time) ?> - <?= html($showing->orig_end_time) ?>
					<? if ($showing->flags): ?>
						| <?= html(strtoupper($showing->flags)) ?>
					<? endif ?>
				</li>
			<? endforeach ?>
		</ul>
	</div>
<? endforeach ?>

<p>Cache is <?= $service->getCacheAge() ?> sec old.</p>

<p><a href="stats.php">Stats</a></p>

<details>
	<summary>Queries (<?= count($db->queries) ?>)</summary>
	<pre><? print_r($db->queries) ?></pre>
</details>
