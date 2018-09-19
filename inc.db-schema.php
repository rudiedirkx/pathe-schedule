<?php

return [
	'version' => 2,
	'tables' => [
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
