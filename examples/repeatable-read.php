<?php declare(strict_types = 1);

namespace Mangoweb\PgDeadlockPlayground;

$verifier = require __DIR__ . '/bootstrap.php';
assert($verifier instanceof ScenarioExpectationVerifier);


$verifier->expectOk(
	Scenario::fromArray([
		['START TRANSACTION ISOLATION LEVEL REPEATABLE READ',   NULL],
		[NULL,                                                  'START TRANSACTION ISOLATION LEVEL REPEATABLE READ'],
		[NULL,                                                  'UPDATE users SET name = \'John\' WHERE id = 1'],
		[NULL,                                                  'COMMIT'],
		['SELECT * FROM users WHERE id = 1 FOR UPDATE',         NULL],
		['COMMIT',                                              NULL],
	])
);

$verifier->expectError(
	'could not serialize access due to concurrent update',
	Scenario::fromArray([
		['START TRANSACTION ISOLATION LEVEL REPEATABLE READ',   NULL],
		['SELECT 1',                                            NULL], // almost same as the 1st but with an extra select
		[NULL,                                                  'START TRANSACTION ISOLATION LEVEL REPEATABLE READ'],
		[NULL,                                                  'UPDATE users SET name = \'John\' WHERE id = 1'],
		[NULL,                                                  'COMMIT'],
		['SELECT * FROM users WHERE id = 1 FOR UPDATE',         NULL],
		['COMMIT',                                              NULL],
	])
);

$verifier->expectError(
	'could not serialize access due to concurrent update',
	Scenario::fromArray([
		['START TRANSACTION ISOLATION LEVEL REPEATABLE READ',   NULL],
		[NULL,                                                  'START TRANSACTION ISOLATION LEVEL REPEATABLE READ'],
		[NULL,                                                  'UPDATE users SET name = \'John\' WHERE id = 1'],
		['SELECT * FROM users WHERE id = 1 FOR UPDATE',         NULL], // almost same as the 1st but with select before commit
		[NULL,                                                  'COMMIT'],
		['COMMIT',                                              NULL],
	])
);

$verifier->expectAlwaysOk(
	Scenario::fromArray([
		['START TRANSACTION ISOLATION LEVEL REPEATABLE READ',   NULL],
		['LOCK users IN SHARE MODE',                            NULL], // almost same as the 1st but with shared lock on users table
		['SELECT * FROM users WHERE id = 1 FOR UPDATE',         NULL],
		['COMMIT',                                              NULL],
		[NULL,                                                  'START TRANSACTION ISOLATION LEVEL REPEATABLE READ'],
		[NULL,                                                  'UPDATE users SET name = \'John\' WHERE id = 1'],
		[NULL,                                                  'COMMIT'],
	])
);

$verifier->expectError(
	'could not serialize access due to concurrent update',
	Scenario::fromArray([
		['START TRANSACTION ISOLATION LEVEL REPEATABLE READ',   NULL],
		['LOCK users IN SHARE MODE',                            NULL], // shared lock is not enough this time
		['SELECT * FROM users WHERE id = 1 FOR UPDATE',         NULL],
		['UPDATE users SET name = \'Dave\' WHERE id = 1',       NULL], // almost same as the previous but with UPDATE in both transactions
		[NULL,                                                  'START TRANSACTION ISOLATION LEVEL REPEATABLE READ'],
		[NULL,                                                  'UPDATE users SET name = \'John\' WHERE id = 1'],
		['COMMIT',                                              NULL],
		[NULL,                                                  'COMMIT'],
	])
);

$verifier->expectError(
	'could not serialize access due to concurrent update',
	Scenario::fromArray([
		['START TRANSACTION ISOLATION LEVEL REPEATABLE READ',   NULL],
		['LOCK users IN ACCESS EXCLUSIVE MODE',                 NULL], // almost same as the previous but with exclusive lock on users table
		['SELECT * FROM users WHERE id = 1 FOR UPDATE',         NULL],
		['UPDATE users SET name = \'Dave\' WHERE id = 1',       NULL],
		[NULL,                                                  'START TRANSACTION ISOLATION LEVEL REPEATABLE READ'],
		[NULL,                                                  'UPDATE users SET name = \'John\' WHERE id = 1'],
		['COMMIT',                                              NULL],
		[NULL,                                                  'COMMIT'],
	])
);

$verifier->expectAlwaysOk(
	Scenario::fromArray([
		['START TRANSACTION ISOLATION LEVEL REPEATABLE READ',   NULL],
		['LOCK users IN ACCESS EXCLUSIVE MODE',                 NULL],
		['SELECT * FROM users WHERE id = 1 FOR UPDATE',         NULL],
		['UPDATE users SET name = \'Dave\' WHERE id = 1',       NULL],
		['COMMIT',                                              NULL],
		[NULL,                                                  'START TRANSACTION ISOLATION LEVEL REPEATABLE READ'],
		[NULL,                                                  'LOCK users IN ACCESS EXCLUSIVE MODE'], // almost same as the previous but with exclusive lock in both transactions
		[NULL,                                                  'UPDATE users SET name = \'John\' WHERE id = 1'],
		[NULL,                                                  'COMMIT'],
	])
);

$verifier->expectError(
	'deadlock detected DETAIL: Process %d% waits for AccessExclusiveLock on relation %d% of database %d%; blocked by process %d%. Process %d% waits for AccessExclusiveLock on relation %d% of database %d%; blocked by process %d%. HINT: See server log for query details.',
	Scenario::fromArray([
		['START TRANSACTION ISOLATION LEVEL REPEATABLE READ',   NULL],
		['LOCK users IN SHARE MODE',                            NULL],
		[NULL,                                                  'START TRANSACTION ISOLATION LEVEL REPEATABLE READ'],
		[NULL,                                                  'LOCK users IN SHARE MODE'],
		['LOCK users IN ACCESS EXCLUSIVE MODE',                 NULL],
		['SELECT * FROM users WHERE id = 1 FOR UPDATE',         NULL],
		['UPDATE users SET name = \'Dave\' WHERE id = 1',       NULL],
		['COMMIT',                                              NULL],
		[NULL,                                                  'LOCK users IN ACCESS EXCLUSIVE MODE'],
		[NULL,                                                  'UPDATE users SET name = \'John\' WHERE id = 1'],
		[NULL,                                                  'COMMIT'],
	])
);
