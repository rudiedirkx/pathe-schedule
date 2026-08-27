<?php

namespace rdx\pathe;

class ScheduleMovie {

	public ?string $status = null;
	/** @var list<Showing> */
	public array $showings = [];

	public function __construct(
		public Movie $movie,
	) {}

	public function addShowing( Showing $showing ) : void {
		$this->showings[] = $showing;
	}

	public function setStatus( string $status ) : void {
		$this->status = $status;
	}

	public function statusToInt() : int {
		if ( $this->status == 'todo' ) {
			return 0;
		}

		if ( $this->status == 'hide' ) {
			return 9;
		}

		return 5;
	}

}
