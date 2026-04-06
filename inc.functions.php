<?php

use Wikimedia\IPSet;

function semidebug() : bool {
	/** @var ?bool */
	static $cache = null;

	if ($cache !== null) return $cache;

	if (!defined('ADMIN_IPS') || !count(ADMIN_IPS)) return $cache = false;

	if (empty($_SERVER['REMOTE_ADDR'])) return $cache = false;

	if (($_SESSION['semidebug'] ?? false) === true) return $cache = true;

	$set = new IPSet(ADMIN_IPS);
	$ip = $_SERVER['REMOTE_ADDR'];
	return $_SESSION['semidebug'] = $debug = $set->match($ip);
}

function html( ?string $text ) : string {
	return htmlspecialchars((string)$text, ENT_QUOTES, 'UTF-8') ?: htmlspecialchars((string)$text, ENT_QUOTES, 'ISO-8859-1');
}

function do_redirect( string $path ) : never {
	header('Location: ' . $path);
	exit;
}
