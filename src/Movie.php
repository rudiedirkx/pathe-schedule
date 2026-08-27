<?php

namespace rdx\pathe;

use DateTime;
use db_generic_model;

class Movie extends db_generic_model {

	static public $_table = 'movies';

	public function getMoreDaysAfter(string $today) : string {
		if (!$this->last_known_date) {
			return 'until ?';
		}

		$days = (new DateTime($this->last_known_date))->diff(new DateTime($today))->days;
		if ($days == 0) {
			return "LAST TIME?";
		}

		return "$days more days";
	}

	protected function relate_last_showing_date() {
		return $this->to_aggregate('showings', 'max(date)', 'movie_id')
			->caster('strval');
	}

	protected function get_pretty_release_date() : string {
		return date('d-M-Y', strtotime($this->release_date));
	}

	protected function get_searchable_name() : string {
		return trim(explode('(', $this->name)[0]);
	}

	public function __toString() {
		return (string) $this->name;
	}

}
