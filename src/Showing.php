<?php

namespace rdx\pathe;

use db_generic_model;

class Showing extends db_generic_model {

	static public $_table = 'showings';

	protected function relate_movie() {
		return $this->to_one(Movie::class, 'movie_id');
	}

	public function __toString() {
		return "{$this->start_time} - {$this->start_time} ({$this->flags})";
	}

}
