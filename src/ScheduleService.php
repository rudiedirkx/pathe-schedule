<?php

namespace rdx\pathe;

use InvalidArgumentException;
use rdx\jsdom\Node;
use Exception;
use GuzzleHttp\Client as Guzzle;

class ScheduleService {

	const DAY_START = '03:00';

	protected $city;
	protected $origDate;
	protected $date;
	protected $time;
	protected $removeFromLabel;
	protected $watchlist;

	public function __construct( string $city, string $date, string $removeFromLabel ) {
		$this->city = $city;
		$this->removeFromLabel = $removeFromLabel;

		$this->date = $date;
		$this->time = date('H:i');

		if ($this->time < self::DAY_START) {
			$this->origDate = date('Y-m-d', strtotime($this->date));
			$this->date = date('Y-m-d', strtotime('-1 days', strtotime($this->date)));

			list($hour, $minute) = explode(':', $this->time);
			$hour += 24;
			$this->time = "$hour:$minute";
		}
		else {
			$this->date = $this->origDate = date('Y-m-d', strtotime($this->date));
		}

		$this->db = $GLOBALS['db'];
	}

	public function getDate() {
		return $this->date;
	}

	public function getTitle() {
		return date('l d-M', strtotime($this->date));
	}

	public function getSchedule() {
		if ( $this->needsFetch() ) {
			$this->fetch();
		}

		if ( $this->origDate == date('Y-m-d') ) {
			$startTime = $this->time;
			$showings = Showing::all("date = ? AND end_time >= ? ORDER BY start_time ASC", [$this->date, $startTime]);
		}
		else {
			$showings = Showing::all('date = ? ORDER BY start_time ASC', [$this->date]);
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

	protected function fetchWatchlist() {
		if ( PATHE_OBJECT_STORE_URL && PATHE_OBJECT_STORE ) {
			$url = PATHE_OBJECT_STORE_URL . '/?store=' . PATHE_OBJECT_STORE . '&get=pathe';
			$guzzle = new Guzzle(['
				timeout' => 5,
			]);
			try {
				$rsp = $guzzle->get($url);
				$json = (string) $rsp->getBody();
				$json = substr($json, strpos($json, '{'));
				$data = json_decode($json, true);
				if ( !empty($data['exists']) ) {
					$this->watchlist = array_map(function($list) {
						return array_map([$this, 'getMovieId'], $list);
					}, $data['value']);

					return true;
				}
			}
			catch (Exception $ex) {
			}
		}

		$this->watchlist = ['todo' => [], 'hide' => []];

		return false;
	}

	protected function getWatchlistStatus( Movie $movie ) {
		if ( in_array($movie->pathe_id, $this->watchlist['todo']) ) {
			return 'todo';
		}

		if ( in_array($movie->pathe_id, $this->watchlist['hide']) ) {
			return 'hide';
		}
	}

	public function getCacheAge() {
		return time() - $this->getLastFetch();
	}

	public function getLastFetch() {
		return $this->db->max('fetches', 'fetched_on', 'date = ?', [$this->date]);
	}

	protected function saveLastFetch() {
		return $this->db->insert('fetches', ['date' => $this->date, 'fetched_on' => time()]);
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
		return "$base/bioscoop/{$this->city}?date={$this->date}";
	}

	public function getMovieUrl( $href ) {
		$base = $this->getBaseUrl();
		if ( $href[0] == '/' ) {
			return "$base$href";
		}

		throw new InvalidArgumentException("Invalid [href] to make movie URL: '$href'");
	}

	public function getMovieId( $href ) {
		if ( preg_match('#^/film/(\d+)/.+#', $href, $match) ) {
			return $match[1];
		}

		throw new InvalidArgumentException("Can't extract movie ID from [href]: '$href'");
	}

	public function getMovieReleaseDate(string $href) : ?string {
		$url = $this->getMovieUrl($href);
		$html = @file_get_contents($url);
		if (!$html) return null;
		$text = strip_tags($html);
		if (preg_match('#Release\s*:\s+(\d+)-(\d+)-(\d{4})#', $text, $match)) {
			return date('Y-m-d', strtotime($match[3] . '-' . $match[2] . '-' . $match[1]));
		}
		return null;
	}

	public function persistMovie( $href, $name ) {
		$id = $this->getMovieId($href);

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
			]));
		}

		if ( !$movie->release_date ) {
			$releaseDate = $this->getMovieReleaseDate($href);
			$movie->update([
				'release_date' => $releaseDate,
			]);
		}

		return $movie;
	}

	public function persistShowing( Movie $movie, $startTime, $endTime, $label ) {
		$startTime = self::timePlus24($startTime) ?? $startTime;
		$endTime = self::timePlus24($endTime) ?? $endTime;

		$label = trim(preg_replace($this->removeFromLabel, '', strtolower($label)));
		$saveLabel = in_array($label, ['uitverkocht']) ? [] : [
			'flags' => $label ?: null,
		];

		$showing = Showing::first([
			'movie_id' => $movie->id,
			'date' => $this->date,
			'start_time' => $startTime,
		]);

		if ( $showing ) {
			$showing->update($saveLabel + [
				'end_time' => $endTime,
				'last_fetch' => time(),
			]);
		}
		else {
			$showing = Showing::find(Showing::insert($saveLabel + [
				'movie_id' => $movie->id,
				'date' => $this->date,
				'start_time' => $startTime,
				'end_time' => $endTime,
				'first_fetch' => time(),
				'last_fetch' => time(),
			]));
		}

		return $showing;
	}

	public function fetch() {
		$url = $this->getScheduleUrl();
		$html = @file_get_contents($url);
		if (!$html) return;
		$crawler = Node::create($html);

		$schedule = $crawler->query('section.schedule-simple');
		if ( !$schedule ) return $this->saveLastFetch();

		$movieNodes = $schedule->children('.schedule-simple__item');
		foreach ($movieNodes as $movieNode) {
			$h4 = $movieNode->query('h4');

			$href = $h4->query('a')['href'];
			$href = preg_replace('/[?#].*$/', '', $href);

			$name = trim($h4->innerText);

			$movie = $this->persistMovie($href, $name);

			$times = $movieNode->queryAll('a.schedule-time');
			foreach ( $times as $timeNode ) {
				$startTime = trim($timeNode->query('.schedule-time__start')->innerText);
				$endTime = trim($timeNode->query('.schedule-time__end')->innerText);
				$label = trim($timeNode->query('.schedule-time__label')->innerText);

				$this->persistShowing($movie, $startTime, $endTime, $label);
			}
		}

		$this->saveLastFetch();
	}

	static public function timePlus24( $time ) {
		if ( $time < ScheduleService::DAY_START ) {
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
