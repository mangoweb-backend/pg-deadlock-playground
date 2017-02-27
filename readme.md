# PostgreSQL Deadlock Playground

A simple tool for playing with multiple concurrent PostgreSQL transactions and testing whether and under what circumstances they may result in deadlock.
The verifier class can automatically test given scenario with all possible orderings.


## Installation

~~~bash
composer require mangoweb/pg-deadlock-playground
~~~


## Documentation

### Creating Scenario

A scenario is an ordered sequence of steps. Every step is defined as a tuple of connection ID and query.

~~~php
$scenario = new Mangoweb\PgDeadlockPlayground\Scenario();
$scenario->addStep(0, 'SELECT 123');     // first execute SELECT 123 on connection #0
$scenario->addStep(1, 'SELECT 456');     // then execute SELECT 456 on connection #1
$scenario->addStep(1, 'SELECT 789');     // then execute SELECT 789 on connection #1
$scenario->addStep(2, 'SELECT \'abc\''); // then execute SELECT 'abc' on connection #2
$scenario->addStep(0, 'SELECT NOW()');   // then execute SELECT NOW on connection #0
~~~

At any point you can dump the scenario with `$scenario->dump()` call. In this case it will print

~~~
SELECT 123
               SELECT 456
               SELECT 789
                            SELECT 'abc'
SELECT NOW()
~~~

Alternatively you can create the same scenario with `Scenario::fromArray`

~~~php
$scenario = Mangoweb\PgDeadlockPlayground\Scenario::fromArray([
    ['SELECT 123',   NULL,        NULL            ],
    [NULL,          'SELECT 456', NULL            ],
    [NULL,          'SELECT 789', NULL            ],
    [NULL,           NULL,        'SELECT \'abc\''],
    ['SELECT NOW()', NULL,        NULL            ],
]);
~~~


### Generating All Scenario Step Orderings

To get all possible step orderings for given scenario call `$scenario->getAllOrderings()`. For example the following code

~~~php
$scenario = Mangoweb\PgDeadlockPlayground\Scenario::fromArray([
	['SELECT 123',   NULL       ],
	[NULL,          'SELECT 456'],
	[NULL,          'SELECT 789'],
]);

foreach ($scenario->getAllOrderings() as $scenarioVariant) {
    $scenarioVariant->dump();
}
~~~

will output

~~~
SELECT 123
             SELECT 456
             SELECT 789
-----------------------
             SELECT 456
SELECT 123
             SELECT 789
-----------------------
             SELECT 456
             SELECT 789
SELECT 123
~~~


### Executing Scenario

To execute a scenario you need an instance of `ScenarioExecutor`.

~~~php
$executor = Mangoweb\PgDeadlockPlayground\ScenarioExecutor::create([
    'dbname' => 'deadlock_playground',
    'user' => 'postgres',
    'password' => '',
]);
~~~

Calling `$executor->execute($scenario)` will always return instance of `ScenarioExecutionResult`. You can inspect the result by calling `$result->dump()`.

~~~php
$result = $executor->execute($scenario);
$result->dump();
~~~


### Understanding Results

The output of `$result->dump()` is similar to `$scenario->dump()` but each step is prefixed with important tag.

| Tag           | Meaning                                                                                  |
| ------------- | ---------------------------------------------------------------------------------------- |
| `OK`          | Query was successfully completed                                                         |
| `FAILED`      | Query failed                                                                             |
| `WAITING...`  | Query execution has started but cannot be completed because it is waiting on a lock      |
| `...SUCCESS`  | A previously waiting query was successfully completed                                    |
| `...FAILURE`  | A previously waiting query failed                                                        |
| `DELAYED`     | Query cannot yet be executed because the previous query is still waiting on a lock       |


## Example

### Usage

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

$executor = Mangoweb\PgDeadlockPlayground\ScenarioExecutor::create($config, $initQueries);
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


### Output

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
