<?php

namespace rdx\pathe;

class ScheduleMovie {

	public $movie;
	public $status;
	public $showings = [];

	public function __construct( Movie $movie ) {
		$this->movie = $movie;
	}

	public function addShowing( Showing $showing ) {
		$this->showings[] = $showing;
	}

	public function setStatus( $status ) {
		$this->status = $status;
	}

	public function statusToInt() {
		if ( $this->status == 'todo' ) {
			return 0;
		}

		if ( $this->status == 'hide' ) {
			return 9;
		}

		return 5;
	}

}
