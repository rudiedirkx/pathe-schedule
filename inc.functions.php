<?php

use Wikimedia\IPSet;

function semidebug() : bool {
	/** @var ?bool */
	static $cache = null;

	if ($cache !== null) return $cache;

	if (!defined('ADMIN_IPS') || !count(ADMIN_IPS)) return $cache = false;

	if (empty($_SERVER['REMOTE_ADDR'])) return $cache = false;

	$set = new IPSet(ADMIN_IPS);
	$ip = $_SERVER['REMOTE_ADDR'];
	return $debug = $set->match($ip);
}

function html( ?string $text ) : string {
	return htmlspecialchars((string)$text, ENT_QUOTES, 'UTF-8') ?: htmlspecialchars((string)$text, ENT_QUOTES, 'ISO-8859-1');
}

function do_redirect( string $path ) : never {
	header('Location: ' . $path);
	exit;
}
