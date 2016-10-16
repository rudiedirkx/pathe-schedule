<?php

use Symfony\Component\DomCrawler\Crawler;

require __DIR__ . '/inc.bootstrap.php';

$city = 'eindhoven';
$date = '';

// header('Content-type: text/plain; charset=utf-8');

$base = 'https://www.pathe.nl';
$url = rtrim("$base/bioscoop/$city/$date", '/');

$html = getHTML($url, $cacheAge);

$crawler = new Crawler($html);

$results = extractMovies($crawler);



?>
<meta name="viewport" content="width=device-width, initial-scale=1" />
<meta charset="utf-8" />
<style>
.todo a {
	color: green;
}
.hide a {
	color: red;
}
.hide ul {
	display: none;
}
</style>

<p>Cache is <?= $cacheAge ?> sec old.</p>

<?php

foreach ($results as $result) {
	$todo = $result->todo ? ' todo' : '';
	$hide = $result->hide ? ' hide' : '';

	echo '<div data-href="' . $result->href . '" class="movie' . $todo . $hide . '">';
	echo '<h3><a href="' . $base . $result->href . '">' . $result->title . '</a></h3>';
	echo '<ul>';
	foreach ($result->times as $time) {
		echo '<li>' . $time . '</li> ';
	}
	echo '</ul>';
	echo '</div>';
}



function extractMovies(Crawler $crawler) {
	list($todos, $hides) = getPrefs();

	$schedule = getScheduleSection($crawler);

	$results = [];
	$schedule->children()->each(function($node) use (&$results, $todos, $hides) {

		$h4 = $node->filter('h4')->first();

		$href = $h4->filter('a')->first()->attr('href');
		$href = preg_replace('/#.*$/', '', $href);

		$title = trim($h4->text());

		$todo = in_array($href, $todos);
		$hide = in_array($href, $hides);

		$results[] = (object) [
			'href' => $href,
			'title' => $title,
			'times' => getMovieTimes($node),
			'todo' => $todo,
			'hide' => $hide,
		];

	});

	usort($results, function($a, $b) {
		if ($a->todo) return -1;
		if ($b->todo) return 1;
		if ($a->hide) return 1;
		if ($b->hide) return -1;
		return 0;
	});

	return $results;
}

function getHTML($url, &$cacheAge = -1) {
	$cacheName = sha1($url);
	if (file_exists($cacheFile = PATHE_DOWNLOAD_DIR . "/$cacheName.html") && ($cacheAge = (time() - filemtime($cacheFile))) < PATHE_DOWNLOAD_TTL) {
		$html = file_get_contents($cacheFile);
	}
	else {
		$cacheAge = 0;
		$html = file_get_contents($url);
		file_put_contents($cacheFile, $html);
	}

	return $html;
}

function getPrefs() {
	$prefs = file_get_contents(PATHE_OBJECT_STORE_URL . '?store=' . PATHE_OBJECT_STORE . '&get=pathe');
	$prefs = json_decode(substr($prefs, strpos($prefs, '{')), true);

	$todos = (array) @$prefs['value']['todo'];
	$hides = (array) @$prefs['value']['hide'];

	return [$todos, $hides];
}

function getMovieTimes(Crawler $node) {
	$times = [];
	foreach ($node->filter('a[data-tooltip]') as $time) {
		$time = preg_replace('#\s+#', ' ', trim($time->textContent));
		$times[] = $time;
	}

	return $times;
}

function getScheduleSection(Crawler $crawler) {
	return $crawler->filter('section.schedule-simple')->first();
}
