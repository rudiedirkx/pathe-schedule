<?php

return [
	'version' => 4,
	'tables' => [
		'fetches' => [
			'id' => ['pk' => true],
			'date' => ['type' => 'date'],
			'fetched_on' => ['unsigned' => true],
		],
		'movies' => [
			'id' => ['pk' => true],
			'pathe_id',
			'name',
			'release_date' => ['type' => 'date'],
			'first_fetch' => ['unsigned' => true, 'default' => 0],
			'last_fetch' => ['unsigned' => true, 'default' => 0],
		],
		'showings' => [
			'id' => ['pk' => true],
			'movie_id' => ['unsigned' => true, 'references' => ['movies', 'id']],
			'date' => ['type' => 'date'],
			'start_time' => ['type' => 'time'],
			'end_time' => ['type' => 'time'],
			'flags',
			'first_fetch' => ['unsigned' => true, 'default' => 0],
			'last_fetch' => ['unsigned' => true, 'default' => 0],
		],
	],
];
