<?php

require __DIR__ . '/env.php';
require __DIR__ . '/vendor/autoload.php';

$db = db_sqlite::open(array('database' => PATHE_DB_FILE));
db_generic_model::$_db = $db;
$db->ensureSchema(require 'inc.db-schema.php');

session_name('patheschedulesession');
session_set_cookie_params([
	'lifetime' => 86400 * 60,
	'path' => '/',
]);
session_start();
if (rand(1, 5) == 1) {
	session_regenerate_id(true);
}
