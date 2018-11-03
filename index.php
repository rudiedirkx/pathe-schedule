<?php

use rdx\pathe\ScheduleService;
use rdx\pathe\Showing;

require __DIR__ . '/inc.bootstrap.php';

$date = $_GET['date'] ?? 'today';
$date = date('Y-m-d', strtotime($date));

$service = new ScheduleService('eindhoven', $date);
$date = $service->getDate();

$movies = $service->getSchedule();

?>
<meta name="viewport" content="initial-scale=1" />
<meta name="theme-color" content="#333" />
<title><?= html($service->getTitle()) ?></title>

<p>
	<a href="./">Today</a> |
	<a href="?date=tomorrow">Tomorrow</a> |
	<a href="?date=2+days">+2</a> |
	<a href="?date=3+days">+3</a>
</p>

<h1><a href="<?= html($service->getScheduleUrl()) ?>"><?= html($service->getTitle()) ?></a></h1>

<? foreach ($movies as $movie): ?>
	<div class="movie">
		<h3><?= html($movie->movie) ?></h3>
		<ul>
			<? foreach ($movie->showings as $showing): ?>
				<li>
					<?= html($showing->start_time) ?> - <?= html($showing->end_time) ?>
					<? if ($showing->flags): ?>
						| <?= html(strtoupper($showing->flags)) ?>
					<? endif ?>
				</li>
			<? endforeach ?>
		</ul>
	</div>
<? endforeach ?>

<p>Cache is <?= $service->getCacheAge() ?> sec old.</p>
