<?php

// To test another Kirby version, install the dev dependencies
// without getkirby/cms and point KIRBY_ROOT to a `kirby` folder:
// KIRBY_ROOT=/path/to/kirby vendor/bin/phpunit
if ($root = getenv('KIRBY_ROOT')) {
	require rtrim($root, '/\\') . '/bootstrap.php';
}

// keep PHPUnit's error handlers
Kirby\Cms\App::$enableWhoops = false;
