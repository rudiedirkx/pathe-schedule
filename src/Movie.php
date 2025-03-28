<?php

namespace rdx\pathe;

use db_generic_model;

/**
 * @property int $id
 * @property int $pathe_id
 * @property string $name
 * @property string $release_date
 * @property int $first_fetch
 * @property int $last_fetch
 */
class Movie extends db_generic_model {

	static public $_table = 'movies';

	protected function get_pretty_release_date() {
		return date('d-M-Y', strtotime($this->release_date));
	}

	public function __toString() {
		return (string) $this->name;
	}

}
