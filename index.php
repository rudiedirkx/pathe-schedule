<?php

require __DIR__ . '/inc.bootstrap.php';

$city = 'eindhoven';
$date = '';

// header('Content-type: text/plain; charset=utf-8');

$url = rtrim("https://www.pathe.nl/bioscoop/$city/$date", '/');

$html = getHTML($url, $cacheAge);



$dom = new DOMDocument;
libxml_use_internal_errors(true);
$dom->loadHTML($html);



$results = extractMovies($dom);



?>
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
	echo '<h3><a href="' . $result->href . '">' . $result->title . '</a></h3>';
	echo '<ul>';
	foreach ($result->times as $time) {
		echo '<li>' . $time . '</li> ';
	}
	echo '</ul>';
	echo '</div>';
}



function extractMovies(DOMDocument $dom) {
	list($todos, $hides) = getPrefs();

	$xpath = new DOMXPath($dom);

	$schedule = getScheduleSection($dom);

	$results = [];
	foreach ($schedule->childNodes as $movie) {
		if (!trim($movie->textContent)) continue;

		$h4 = $movie->getElementsByTagName('h4')[0];

		$href = trim($h4->getElementsByTagName('a')[0]->attributes->getNamedItem('href')->nodeValue);
		$href = preg_replace('/#.*$/', '', $href);

		$title = trim($h4->textContent);

		$todo = in_array($href, $todos);
		$hide = in_array($href, $hides);

		$result = (object) [
			'href' => $href,
			'title' => $title,
			'times' => getMovieTimes($xpath, $movie),
			'todo' => $todo,
			'hide' => $hide,
		];

		$results[] = $result;
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
	if (file_exists($cacheFile = __DIR__ . "/cache/$cacheName.html") && ($cacheAge = (time() - filemtime($cacheFile))) < 300) {
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

function getMovieTimes(DOMXPath $xpath, DOMElement $movie) {
	$nodes = $xpath->query($movie->getNodePath() . '//a[@data-tooltip]', $movie);

	$times = [];
	foreach ($nodes as $time) {
		$time = preg_replace('#\s+#', ' ', trim($time->textContent));
		$times[] = $time;
	}

	return $times;
}

function getScheduleSection(DOMDocument $dom) {
	$sections = $dom->getElementsByTagName('section');
	foreach ($sections as $section) {
		$class = $section->attributes->getNamedItem('class');
		$class = $class ? (string) $class->nodeValue : '';
		if ($class && preg_match('#(^|\s)schedule\-simple(\s|$)#', $class)) {
			return $section;
		}
	}
}
