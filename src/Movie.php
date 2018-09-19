<?php

namespace rdx\pathe;

use db_generic_model;

class Movie extends db_generic_model {

	static public $_table = 'movies';

	public function __toString() {
		return (string) $this->name;
	}

}
