<?php

namespace rdx\pathe;

use db_generic_model;

class Showing extends db_generic_model {

	static public $_table = 'showings';

	protected function get_orig_start_time() {
		return ScheduleService::timeMinus24($this->start_time) ?? $this->start_time;
	}

	protected function get_orig_end_time() {
		return ScheduleService::timeMinus24($this->end_time) ?? $this->end_time;
	}

	protected function relate_movie() {
		return $this->to_one(Movie::class, 'movie_id');
	}

	public function __toString() {
		return "{$this->start_time} - {$this->start_time} ({$this->flags})";
	}

}
