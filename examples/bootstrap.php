<?php declare(strict_types = 1);

namespace Mangoweb\PgDeadlockPlayground;

require __DIR__ . '/../vendor/autoload.php';

ini_set('assert.exception', '1');

$config = json_decode(file_get_contents(__DIR__ . '/config.json'), TRUE);

$initQueries = [
	'DROP TABLE IF EXISTS users',
	'CREATE TABLE users (id INTEGER NOT NULL, name TEXT NOT NULL)',
	'INSERT INTO users VALUES (1, \'Logan\')'
];

$executor = ScenarioExecutor::create($config, $initQueries);
$verifier = new ScenarioExpectationVerifier($executor);
$verifier->setVerbose();

return $verifier;
