<?php declare(strict_types = 1);

namespace Mangoweb\PgDeadlockPlayground;


class ScenarioExecutionProcess
{
	/** @var ConnectionPool */
	private $pool;

	/** @var Scenario */
	private $scenario;

	/** @var ScenarioExecutionResultBuilder */
	private $resultBuilder;

	/** @var array */
	private $steps;

	/** @var array */
	private $waiting;


	public function __construct(ConnectionPool $pool, Scenario $scenario)
	{
		$this->pool = $pool;
		$this->scenario = $scenario;
	}


	public function run(): ScenarioExecutionResult
	{
		$this->resultBuilder = new ScenarioExecutionResultBuilder();
		$this->steps = $this->scenario->getSteps();
		$this->waiting = [];

		try {
			while ($this->steps) {
				$this->fetchFinishedResults();
				$this->findNextStep();
				$this->executeNextStep();
			}

		} catch (QueryException $e) {
			$this->processException($e);

		} finally {
			$this->pool->destroy();
		}

		return $this->resultBuilder->build();
	}


	private function fetchFinishedResults(): void
	{
		foreach ($this->waiting as $connectionIdx => $_) {
			try {
				$this->waitForIdleConnection($connectionIdx);
				if ($this->fetchResultIfReady($connectionIdx)) {
					$this->resultBuilder->addCompletedResultFetch($connectionIdx);
				}

			} catch (QueryException $e) {
				$this->resultBuilder->addFailedResultFetch($connectionIdx);
				throw $e;
			}
		}
	}


	private function findNextStep(): void
	{
		$delayedSteps = [];
		$nextStep = NULL;
		$futureSteps = [];

		foreach ($this->steps as $step) {
			if ($nextStep !== NULL) {
				$futureSteps[] = $step;

			} elseif (isset($this->waiting[$step[0]])) {
				$delayedSteps[] = $step;
				$this->resultBuilder->addDelayedStep(...$step);

			} elseif ($delayedSteps) {
				$nextStep = $step;

			} else {
				return;
			}
		}

		if ($nextStep === NULL) {
			throw new \LogicException('PostgreSQL should have detected deadlock');

		} else {
			$this->steps = array_merge([$nextStep], $delayedSteps, $futureSteps);
		}
	}


	private function executeNextStep(): void
	{
		[$connectionIdx, $query] = array_shift($this->steps);

		try {
			$this->sendQuery($connectionIdx, $query);
			$this->waitForIdleConnection($connectionIdx);

			if ($this->fetchResultIfReady($connectionIdx)) {
				$this->resultBuilder->addCompletedStep($connectionIdx, $query);

			} else {
				$this->resultBuilder->addWaitingStep($connectionIdx, $query);
			}

		} catch (QueryException $e) {
			$this->resultBuilder->addFailedStep($connectionIdx, $query);
			throw $e;
		}
	}


	private function processException(QueryException $exception): void
	{
		$this->resultBuilder->addException($exception);

		foreach ($this->steps as $step) {
			$this->resultBuilder->addFutureStep(...$step);
		}
	}


	private function sendQuery(int $connectionIdx, string $query): void
	{
		$connection = $this->pool->get($connectionIdx);

		if (!pg_send_query($connection, $query)) { // sends query without waiting for result
			throw new QueryException(pg_last_error($connection));
		}

		$this->waiting[$connectionIdx] = $query;
	}


	private function waitForIdleConnection(int $connectionIdx): void
	{
		$connection = $this->pool->get($connectionIdx);
		$waitTimes = [100, 900, 9000];

		for ($i = 0; $i < count($waitTimes) && pg_connection_busy($connection); $i++) {
			usleep($waitTimes[$i]);
		}
	}


	private function fetchResultIfReady(int $connectionIdx): bool
	{
		assert(isset($this->waiting[$connectionIdx]));
		$connection = $this->pool->get($connectionIdx);

		if (!pg_connection_busy($connection)) {
			$this->fetchResult($connectionIdx);
			return TRUE;

		} else {
			return FALSE;
		}
	}


	private function fetchResult(int $connectionIdx): void
	{
		assert(isset($this->waiting[$connectionIdx]));
		$connection = $this->pool->get($connectionIdx);

		$result = pg_get_result($connection);
		if (!$result) {
			throw new QueryException(pg_last_error($connection));
		}

		if (pg_result_error_field($result, PGSQL_DIAG_SQLSTATE)) {
			throw new QueryException(pg_last_error($connection));
		}

		unset($this->waiting[$connectionIdx]);
	}
}
