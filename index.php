<?php

use rdx\pathe\ScheduleService;
use rdx\pathe\Showing;

require __DIR__ . '/inc.bootstrap.php';

$date = $_GET['date'] ?? 'today';
$date = date('Y-m-d', strtotime($date));

$service = new ScheduleService('eindhoven', $date);

$movies = $service->getSchedule();

?>
<title><?= html($date) ?></title>

<? foreach ($movies as $movie): ?>
	<div class="movie">
		<h3><?= html($movie->movie) ?></h3>
		<ul>
			<? foreach ($movie->showings as $showing): ?>
				<li><?= html($showing->start_time) ?></li>
			<? endforeach ?>
		</ul>
	</div>
<? endforeach ?>

<p>Cache is <?= $service->getCacheAge() ?> sec old.</p>
