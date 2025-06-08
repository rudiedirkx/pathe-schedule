<?php

use rdx\pathe\ScheduleService;
use rdx\pathe\Showing;

require __DIR__ . '/inc.bootstrap.php';

$date = $_GET['date'] ?? 'today';

$service = new ScheduleService('pathe-eindhoven', $date, '#\b(relax seat)\b#');
$date = $service->getDate();

$movies = $service->getSchedule();

if (isset($_POST['watchlist'])) {
	$args = explode(':', $_POST['watchlist'], 2);
	$service->toggleWatchlist(...$args);
	return do_redirect($_SERVER['HTTP_REFERER']);
}

$title = $service->getTitle();
include 'tpl.header.php';

?>

<p>
	<a href="./">Today</a> |
	<a href="?date=tomorrow">Tomorrow</a>
	<? for ($i=2; $i<=7; $i++): ?>
		| <a href="?date=<?= $i ?>+days">+<?= $i ?></a>
	<? endfor ?>
</p>

<h1><a href="<?= html($service->getScheduleUrl()) ?>"><?= html($service->getTitle()) ?></a></h1>

<? foreach ($movies as $movie): ?>
	<div class="movie <?= $movie->status ?>">
		<h3>
			<?= html($movie->movie) ?>
			(<?= $movie->movie->pretty_release_date ?>)
			<? if (IMDB_SEARCH_URL): ?>
				<a class="icon" target="_blank" href="<?= sprintf(IMDB_SEARCH_URL, urlencode($movie->movie)) ?>">🔎</a>
			<? endif ?>
			<? if (semidebug() && $service->hasWatchlist()): ?>
				<form method="post" action>
					<button class="icon" name="watchlist" value="todo:<?= $movie->movie->pathe_id ?>">❤️</button>
				</form>
				<form method="post" action>
					<button class="icon" name="watchlist" value="hide:<?= $movie->movie->pathe_id ?>">🗑️</button>
				</form>
			<? endif ?>
		</h3>
		<ul>
			<? foreach ($movie->showings as $showing): ?>
				<li>
					<?= html($showing->orig_start_time) ?> - <?= html($showing->orig_end_time) ?>
					<? if ($showing->flags): ?>
						| <?= html(strtoupper($showing->flags)) ?>
					<? endif ?>
					<?if ($showing->progress > 0): ?>
						<div class="progress"><div class="done" style="width: <?= $showing->progress ?>%"></div></div>
					<? endif ?>
				</li>
			<? endforeach ?>
		</ul>
	</div>
<? endforeach ?>

<p>Cache is <?= $service->getCacheAge() ?> sec old.</p>

<p><a href="stats.php">Stats</a></p>

<? if (semidebug()): ?>
	<details>
		<summary>Requests (<?= count($service->requests) ?>)</summary>
		<pre><?= html(print_r($service->requests, true)) ?></pre>
	</details>

	<details>
		<summary>Queries (<?= count($db->queries) ?>)</summary>
		<pre><?= html(print_r($db->queries, true)) ?></pre>
	</details>

	<? $watchlist = $service->getWatchlist() ?>
	<details>
		<summary>Watchlist (<?= count($watchlist['todo']) ?> + <?= count($watchlist['hide']) ?>)</summary>
		<? dump($watchlist) ?>
	</details>
<? endif ?>
