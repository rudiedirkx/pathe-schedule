<?php

namespace rdx\pathe;

use InvalidArgumentException;
use rdx\jsdom\Node;

class ScheduleService {

	protected $city;
	protected $date;
	protected $watchlist;

	public function __construct( $city, $date ) {
		$this->city = $city;
		$this->date = max(date('Y-m-d'), $date);

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

		if ( $this->date == date('Y-m-d') ) {
			$startTime = date('H:i');
			$showings = Showing::all('date = ? AND end_time >= ? ORDER BY start_time ASC', [$this->date, $startTime]);
		}
		else {
			$showings = Showing::all('date = ? ORDER BY start_time ASC', [$this->date]);
		}
		Showing::eager('movie', $showings);

		$watchlist = $this->fetchWatchlist();

		$movies = [];
		foreach ( $showings as $showing ) {
			if ( !isset($movies[$showing->movie_id]) ) {
				$movies[$showing->movie_id] = new ScheduleMovie($showing->movie);
				$movies[$showing->movie_id]->setStatus($this->getWatchlistStatus($showing->movie));
			}

			$movies[$showing->movie_id]->addShowing($showing);
		}

		if ( $watchlist ) {
			usort($movies, function(ScheduleMovie $a, ScheduleMovie $b) {
				return $a->statusToInt() <=> $b->statusToInt();
			});
		}

		return $movies;
	}

	protected function fetchWatchlist() {
		if ( PATHE_OBJECT_STORE_URL && PATHE_OBJECT_STORE ) {
			$url = PATHE_OBJECT_STORE_URL . '/?store=' . PATHE_OBJECT_STORE . '&get=pathe';
			$json = file_get_contents($url);
			$json = substr($json, strpos($json, '{'));
			$data = json_decode($json, true);
			if ( $data['exists'] ) {
				$this->watchlist = array_map(function($list) {
					return array_map([$this, 'getMovieId'], $list);
				}, $data['value']);

				return true;
			}
		}

		$this->watchlist += ['todo' => [], 'hide' => []];

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

	public function getMovieReleaseDate( $href ) {
		$url = $this->getMovieUrl($href);
		$html = file_get_contents($url);
		$text = strip_tags($html);
		if ( preg_match('#Release\s*:\s+(\d+)-(\d+)-(\d{4})#', $text, $match) ) {
			return date('Y-m-d', strtotime($match[3] . '-' . $match[2] . '-' . $match[1]));
		}
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
		$showing = Showing::first([
			'movie_id' => $movie->id,
			'date' => $this->date,
			'start_time' => $startTime,
		]);

		if ( $showing ) {
			$showing->update([
				'end_time' => $endTime,
				'flags' => strtolower($label) ?: null,
				'last_fetch' => time(),
			]);
		}
		else {
			$showing = Showing::find(Showing::insert([
				'movie_id' => $movie->id,
				'date' => $this->date,
				'start_time' => $startTime,
				'end_time' => $endTime,
				'flags' => strtolower($label) ?: null,
				'first_fetch' => time(),
				'last_fetch' => time(),
			]));
		}

		return $showing;
	}

	public function fetch() {
		$url = $this->getScheduleUrl();
		$html = file_get_contents($url);
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

}
