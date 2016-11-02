<?php

use rdx\jsdom\Node;

require __DIR__ . '/inc.bootstrap.php';

$city = 'eindhoven';
$date = @$_GET['date'] ?: '';

$date and $date = date('Y-m-d', strtotime($date));

// header('Content-type: text/plain; charset=utf-8');

$base = 'https://www.pathe.nl';
$url = rtrim("$base/bioscoop/$city/$date", '/');

$html = getHTML($url, $cacheAge);

$crawler = Node::create($html);

$results = extractMovies($crawler);

?>
<meta name="viewport" content="width=device-width, initial-scale=1" />
<meta charset="utf-8" />
<style>
html, body {
	background: #ffc426;
	color: black;
}
a {
    color: black;
}

.movie {
    background: black;
    margin-bottom: 10px;
    padding: 10px;
    color: white;
}
.movie > h3 {
    margin-top: 0;
}
.movie > ul,
.movie.hide > h3 {
    margin-bottom: 0;
}

.movie a {
    color: lightblue;
}
.movie.todo a {
	color: green;
}
.movie.hide a {
	color: red;
}

.hide ul {
	display: none;
}
</style>

<p><?= date('D d-M-Y', $date ? strtotime($date) : time()) ?> | <a href="./">Today</a> | <a href="?date=tomorrow">Tomorrow</a></p>

<p><a href="<?= $url ?>">Pathe.nl</a></p>

<?php

if (empty($results)) {
	echo '<p>No movies found...</p>';
}

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

?>
<p>Cache is <?= $cacheAge ?> sec old.</p>
<?php



function extractMovies(Node $crawler) {
	list($todos, $hides) = getPrefs();

	$schedule = getScheduleSection($crawler);
	if (!$schedule) {
		return [];
	}

	$results = [];
	foreach ($schedule->children() as $node) {

		$h4 = $node->query('h4');

		$href = $h4->query('a')['href'];
		$href = preg_replace('/#.*$/', '', $href);

		$title = trim($h4->innerText);

		$todo = in_array($href, $todos);
		$hide = in_array($href, $hides);

		$results[] = (object) [
			'href' => $href,
			'title' => $title,
			'times' => getMovieTimes($node),
			'todo' => $todo,
			'hide' => $hide,
		];

	}

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

function getMovieTimes(Node $node) {
	$times = [];
	foreach ($node->queryAll('a[data-tooltip]') as $time) {
		$times[] = $time->innerText;
	}

	return $times;
}

function getScheduleSection(Node $crawler) {
	return $crawler->query('section.schedule-simple');
}
