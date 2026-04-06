<?php

namespace rdx\pathe;

use db_generic;
use Exception;
use GuzzleHttp\Client as Guzzle;
use InvalidArgumentException;
use rdx\jsdom\Node;

class ScheduleService {

	public const DAY_START = '03:00';
	protected const SHOW_MINS_AFTER_END = 30;
	protected const KEEP_LABELS = [
		'3d',
		'4dx',
		'50plus',
		'arthouse',
		'classics',
		'film+',
		'imax',
		'kleuter',
		'ladiesnight',
		'music',
		'opera',
		'pridenight',
		'sneaknight',
		'soundsessions',
	];

	protected db_generic $db;
	protected Guzzle $guzzle;

	protected string $city;
	// protected string $todayRealDate;
	protected string $todayScheduleDate;
	protected string $realDate;
	protected string $scheduleDate;
	protected string $time;
	protected array $watchlist;
	protected int $lastFetch;

	public array $requests = [];

	public function __construct( string $city, string $date ) {
		$this->city = $city;

		$this->time = date('H:i');

		// $this->todayRealDate = date('Y-m-d');
		$this->todayScheduleDate = date('Y-m-d', strtotime($this->time < self::DAY_START ? '-1 days' : 'now'));

		if ($date == 'today' && $this->time < self::DAY_START) {
			$this->realDate = date('Y-m-d', strtotime($date));
			$this->scheduleDate = date('Y-m-d', strtotime('-1 days', strtotime($date)));

			$this->time = self::timePlus24($this->time);
		}
		else {
			$this->scheduleDate = $this->realDate = date('Y-m-d', strtotime($date));
		}

		$this->db = $GLOBALS['db'];

		$this->guzzle = new Guzzle([
			'timeout' => 5,
			// 'verify' => false,
			'headers' => [
				'User-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/134.0.0.0 Safari/537.36',
			],
		]);
	}

	public function getDate() {
		return $this->scheduleDate;
	}

	public function getDatesBaseUtc() {
		if (date('H:i') < self::DAY_START) {
			return strtotime('-24 hours');
		}
		return time();
	}

	public function getTitle() {
		return date('l d-M', strtotime($this->scheduleDate));
	}

	public function getSchedule() {
		if ( $this->needsFetch() ) {
			$this->fetch();
		}

		if ( $this->scheduleDate == $this->todayScheduleDate ) {
			$time = date('H:i', strtotime('-' . self::SHOW_MINS_AFTER_END . ' minutes', strtotime($this->time)));
			$time = self::timePlus24($time) ?? $time;
			$showings = Showing::all("date = ? AND end_time >= ? ORDER BY start_time ASC", [$this->scheduleDate, $time]);
		}
		else {
			$showings = Showing::all('date = ? ORDER BY start_time ASC', [$this->scheduleDate]);
		}
		Showing::eager('movie', $showings);

		$useWatchlist = $this->fetchWatchlist();

		$movies = [];
		foreach ( $showings as $showing ) {
			if ( !isset($movies[$showing->movie_id]) ) {
				$movies[$showing->movie_id] = new ScheduleMovie($showing->movie);
				$movies[$showing->movie_id]->setStatus($this->getWatchlistStatus($showing->movie));
			}

			$movies[$showing->movie_id]->addShowing($showing);
		}

		usort($movies, function(ScheduleMovie $a, ScheduleMovie $b) use ($useWatchlist) {
			$watchlistOrder = 0;
			if ( $useWatchlist ) {
				$watchlistOrder = $a->statusToInt() <=> $b->statusToInt();
			}

			if ( $watchlistOrder != 0 ) {
				return $watchlistOrder;
			}

			$dateOrder = $a->movie->release_date <=> $b->movie->release_date;
			return $dateOrder;
		});

		return $movies;
	}

	public function showingIsOutdated(Showing $showing) : bool {
		if ($this->time >= $showing->start_time) return false;
		return $showing->last_fetch + 120 < $this->getLastFetch();
	}

	public function hasWatchlist() : bool {
		return PATHE_OBJECT_STORE_URL && PATHE_OBJECT_STORE;
	}

	protected function fetchWatchlist() {
		if ( $this->hasWatchlist() ) {
			$url = PATHE_OBJECT_STORE_URL . '?store=' . PATHE_OBJECT_STORE . '&get=pathe';
			try {
				$this->requests[] = $url;
				$rsp = $this->guzzle->get($url);
				$json = (string) $rsp->getBody();
				$json = substr($json, strpos($json, '{'));
				$data = json_decode($json, true);

				if ( !empty($data['exists']) ) {
					$this->watchlist = $data['value'];
					// $this->watchlist = array_map(function(array $list) {
					// 	return array_values(array_filter(array_map($this->getMovieId(...), $list)));
					// }, $data['value']);

					return true;
				}
			}
			catch (Exception $ex) {
			}
		}

		$this->watchlist = ['todo' => [], 'hide' => []];

		return false;
	}

	public function getWatchlist() : array {
		return $this->watchlist;
	}

	public function getPrettyWatchlist() : array {
		$allIds = array_merge(...array_values($this->getWatchlist()));
		$movies = Movie::all(['pathe_id' => $allIds]);
		Movie::eagers($movies, ['last_showing_date']);
		$movies = array_reduce($movies, function(array $list, Movie $movie) {
			return $list + [$movie->pathe_id => $movie];
		}, []);
		return array_map(function(array $ids) use ($movies) {
			return array_map(function(string $id) use ($movies) {
				if (!isset($movies[$id])) return '?';
				$movie = $movies[$id];
				return sprintf('%s (%s - %s)', $movie, $movie->release_date, $movie->last_showing_date);
			}, array_combine($ids, $ids));
		}, $this->watchlist);
	}

	protected function getWatchlistStatus( Movie $movie ) : string {
		foreach (['todo', 'hide'] as $bucket) {
			foreach ($this->watchlist[$bucket] as $item) {
				if ($item == $movie->pathe_id) {
					return $bucket;
				}
			}
		}

		return '';
	}

	public function toggleWatchlist( string $bucket, string $patheId ) : void {
		$method = in_array($patheId, $this->watchlist[$bucket]) ? 'pull' : 'push';
		$url = sprintf('%s?store=%s&%s=pathe.%s&value="%s"', PATHE_OBJECT_STORE_URL, PATHE_OBJECT_STORE, $method, $bucket, $patheId);
		$rsp = $this->guzzle->get($url);
		$json = (string) $rsp->getBody();
		if (!json_decode($json)) {
			dd($json);
		}

		if ($bucket == 'hide' and in_array($patheId, $this->watchlist['todo'])) {
			$this->toggleWatchlist('todo', $patheId);
		}
	}

	public function getCacheAgeMinutes() {
		return round((time() - $this->getLastFetch()) / 60);
	}

	public function getLastFetch() {
		return $this->lastFetch ??= (int) $this->db->max('fetches', 'fetched_on', 'date = ?', [$this->scheduleDate]);
	}

	protected function saveLastFetch() : void {
		$this->lastFetch = time();
		$this->db->insert('fetches', ['date' => $this->scheduleDate, 'fetched_on' => $this->lastFetch]);
	}

	public function needsFetch() {
		$lastFetch = $this->getLastFetch();
		return !$lastFetch || $lastFetch < time() - PATHE_DOWNLOAD_TTL;
	}

	public function getBaseUrl() {
		return "https://www.pathe.nl";
	}

	public function getScheduleUrl() {
		$base = $this->getBaseUrl();
		return "$base/bioscoop/{$this->city}?date={$this->scheduleDate}";
	}

	public function getMovieUrl( $href ) {
		$base = $this->getBaseUrl();
		if ( $href[0] == '/' ) {
			return "$base$href";
		}

		throw new InvalidArgumentException("Invalid [href] to make movie URL: '$href'");
	}

	public function getMovieId( string $href ) : ?int {
		// 2025
		if ( preg_match('#/films/[^/]+\-(\d+)(/|\?|\#|$)#', $href, $match) ) {
			return (int) $match[1];
		}

		// older
		if ( preg_match('#^/film/(\d+)/.+#', $href, $match) ) {
			return (int) $match[1];
		}

		return null;
	}

	public function persistMovie( string $href, string $name, string $releaseDate ) : Movie {
		$id = $this->getMovieId($href);
		if ( !$id ) {
			throw new InvalidArgumentException("Can't extract movie ID from [href]: '$href'");
		}

		$movie = Movie::first(['pathe_id' => $id]);

		if ( $movie ) {
			$movie->update([
				'last_fetch' => time(),
			]);
		}
		else {
			$movie = Movie::find(Movie::insert([
				'pathe_id' => $id,
				'name' => $name,
				'first_fetch' => time(),
				'last_fetch' => time(),
				'release_date' => $releaseDate,
			]));
		}

		return $movie;
	}

	public function persistShowing( Movie $movie, string $startTime, string $endTime, array $labels ) : Showing {
		$startTime = self::timePlus24($startTime) ?? $startTime;
		$endTime = self::timePlus24($endTime) ?? $endTime;

		$labels = array_unique(array_map(strtolower(...), $labels));
		$labels = array_intersect($labels, self::KEEP_LABELS);

		$showing = Showing::first([
			'movie_id' => $movie->id,
			'date' => $this->scheduleDate,
			'start_time' => $startTime,
		]);

		if ( $showing ) {
			$showing->update([
				'flags' => implode(' ', $labels),
				'end_time' => $endTime,
				'last_fetch' => time(),
			]);
		}
		else {
			$showing = Showing::find(Showing::insert([
				'flags' => implode(' ', $labels),
				'movie_id' => $movie->id,
				'date' => $this->scheduleDate,
				'start_time' => $startTime,
				'end_time' => $endTime,
				'first_fetch' => time(),
				'last_fetch' => time(),
			]));
		}

		return $showing;
	}

	public function fetch() {
		$url = 'https://www.pathe.nl/api/shows?language=nl';
		$this->requests[] = $url;
		$rsp = $this->guzzle->get($url);
		$json = (string) $rsp->getBody();
		$data = json_decode($json, true);
		$movies = [];
		foreach ($data['shows'] as $show) {
			$movies[ $show['slug'] ] = $show;
		}

		$url = sprintf('https://www.pathe.nl/api/cinema/%s/shows?language=nl', $this->city);
		$this->requests[] = $url;
		$rsp = $this->guzzle->get($url);
		$json = (string) $rsp->getBody();
		$data = json_decode($json, true);

		$shows = array_filter(array_map(function(array $info) {
			return $info['days'][$this->scheduleDate] ?? null;
		}, $data['shows']));

		foreach ($shows as $slug => $info) {
			usleep(1000 * rand(100, 300));

			$url = sprintf(
				'https://www.pathe.nl/api/show/%s/showtimes/%s/%s?language=nl',
				$slug,
				$this->city,
				$this->scheduleDate,
			);
			$this->requests[] = $url;
			$rsp = $this->guzzle->get($url);
			$json = (string) $rsp->getBody();
			$times = json_decode($json, true);

			$movie = $this->persistMovie(
				sprintf('https://www.pathe.nl/nl/films/%s', $slug),
				$movies[$slug]['title'],
				max($movies[$slug]['releaseAt']),
			);

			foreach ($times as $time) {
				$this->persistShowing(
					$movie,
					date('H:i', strtotime($time['time'])),
					date('H:i', strtotime($time['endTime'])),
					array_filter([...$time['tags'], $info['flag'] ?? '']),
				);
			}
		}

		$this->saveLastFetch();
	}

	static public function timePlus24( $time ) {
		if ( $time < self::DAY_START ) {
			list($hour, $minute) = explode(':', $time);
			$hour += 24;
			return "$hour:$minute";
		}
	}

	static public function timeMinus24( $time ) {
		if ($time >= '24:00') {
			list($hour, $minute) = explode(':', $time);
			$hour -= 24;
			return str_pad($hour, 2, '0', STR_PAD_LEFT) . ":$minute";
		}
	}

}
