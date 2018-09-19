<?php

require __DIR__ . '/env.php';
require __DIR__ . '/vendor/autoload.php';

$db = db_sqlite::open(array('database' => __DIR__ . '/db/pathe.sqlite3'));
$db->ensureSchema(require 'inc.db-schema.php');
db_generic_model::$_db = $db;
