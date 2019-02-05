<?php

require __DIR__ . '/env.php';
require __DIR__ . '/vendor/autoload.php';

$db = db_sqlite::open(array('database' => PATHE_DB_FILE));
db_generic_model::$_db = $db;
$db->ensureSchema(require 'inc.db-schema.php');
