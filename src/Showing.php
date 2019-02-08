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

	protected function get_progress() {
		$now = date('Y-m-d H:i:s');

		if ( "$this->date $this->start_time" > $now ) {
			return 0;
		}
		elseif ( "$this->date $this->end_time" < $now ) {
			return 100;
		}

		$start = explode(':', $this->start_time);
		$start = 60 * $start[0] + $start[1];

		$end = explode(':', $this->end_time);
		$end = 60 * $end[0] + $end[1];

		$now = ScheduleService::timePlus24(date('H:i')) ?? date('H:i');
		$now = explode(':', $now);
		$now = 60 * $now[0] + $now[1];

// var_dump($start, $end, $now);

		return round(($now - $start) / ($end - $start) * 100);
	}

	protected function relate_movie() {
		return $this->to_one(Movie::class, 'movie_id');
	}

	public function __toString() {
		return "{$this->start_time} - {$this->start_time} ({$this->flags})";
	}

}
