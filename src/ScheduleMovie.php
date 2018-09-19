<?php

namespace rdx\pathe;

class ScheduleMovie {

	public $movie;
	public $showings = [];

	public function __construct( Movie $movie ) {
		$this->movie = $movie;
	}

	public function addShowing( Showing $showing ) {
		$this->showings[] = $showing;
	}

}
