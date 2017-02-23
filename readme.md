# PostgreSQL Deadlock Playground

A simple tool for playing with multiple concurrent PostgreSQL transactions and testing whether and under what circumstances they may result in deadlock.
The verifier class can automatically test given scenario with all possible orderings.


## Installation

~~~bash
composer require mangoweb/pg-deadlock-playground
~~~


## Usage Example

~~~php
$config = [
    'host' => '127.0.0.1',
    'dbname' => 'deadlock_playground',
    'user' => 'postgres',
    'password' => '',
];

$initQueries = [
    'DROP TABLE IF EXISTS users',
    'CREATE TABLE users (id INTEGER NOT NULL, name TEXT NOT NULL)',
    'INSERT INTO users VALUES (1, \'Logan\')'
];

$connectionFactory = new Mangoweb\PgDeadlockPlayground\ConnectionFactory($config);
$connectionPoolFactory = new Mangoweb\PgDeadlockPlayground\ConnectionPoolFactory($connectionFactory, $initQueries);
$executor = new Mangoweb\PgDeadlockPlayground\ScenarioExecutor($connectionPoolFactory);
$verifier = new Mangoweb\PgDeadlockPlayground\ScenarioExpectationVerifier($executor);
$verifier->setVerbose();

$verifier->expectAlwaysOk(
    Scenario::fromArray([
        ['START TRANSACTION ISOLATION LEVEL REPEATABLE READ',   NULL],
        ['LOCK users IN SHARE MODE',                            NULL],
        ['SELECT * FROM users WHERE id = 1 FOR UPDATE',         NULL],
        ['COMMIT',                                              NULL],
        [NULL,                                                  'START TRANSACTION ISOLATION LEVEL REPEATABLE READ'],
        [NULL,                                                  'UPDATE users SET name = \'John\' WHERE id = 1'],
        [NULL,                                                  'COMMIT'],
    ])
);
~~~


## Output Example

~~~
...
SUCCESS: completed without error
in C:\Projects\deadlock-playground\examples\readme.php:25

   [OK]         START TRANSACTION ISOLATION LEVEL REPEATABLE READ
   [OK]         LOCK users IN SHARE MODE
   [OK]         SELECT * FROM users WHERE id = 1 FOR UPDATE
                                                                    [OK]         START TRANSACTION ISOLATION LEVEL REPEATABLE READ
   [OK]         COMMIT
                                                                    [OK]         UPDATE users SET name = 'John' WHERE id = 1
                                                                    [OK]         COMMIT
----------------------------------------------------------------------------------------------------------------------------------

SUCCESS: completed without error
in C:\Projects\deadlock-playground\examples\readme.php:25

   [OK]         START TRANSACTION ISOLATION LEVEL REPEATABLE READ
   [OK]         LOCK users IN SHARE MODE
   [OK]         SELECT * FROM users WHERE id = 1 FOR UPDATE
                                                                    [OK]         START TRANSACTION ISOLATION LEVEL REPEATABLE READ
                                                                    [WAITING...] UPDATE users SET name = 'John' WHERE id = 1
   [OK]         COMMIT
                                                                    [...SUCCESS]
                                                                    [OK]         COMMIT
...
~~~
