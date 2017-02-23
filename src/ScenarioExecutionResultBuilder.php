<?php declare(strict_types = 1);

namespace Mangoweb\PgDeadlockPlayground;


class ScenarioExecutionResultBuilder
{
	/** @var array */
	private $steps = [];

	/** @var int */
	private $successfulStepsCount = 0;

	/** @var NULL|QueryException */
	private $exception;


	public function addCompletedStep(int $connectionIdx, string $query): void
	{
		$this->steps[] = [$connectionIdx, $query, 'OK'];
		$this->successfulStepsCount++;
	}


	public function addFailedStep(int $connectionIdx, string $query): void
	{
		$this->steps[] = [$connectionIdx, $query, 'FAILED'];
	}


	public function addWaitingStep(int $connectionIdx, string $query): void
	{
		$this->steps[] = [$connectionIdx, $query, 'WAITING...'];
		$this->successfulStepsCount++;
	}


	public function addDelayedStep(int $connectionIdx, string $query): void
	{
		$this->steps[] = [$connectionIdx, $query, 'DELAYED'];
		$this->successfulStepsCount++;
	}


	public function addFutureStep(int $connectionIdx, string $query): void
	{
		$this->steps[] = [$connectionIdx, $query, ''];
	}


	public function addCompletedResultFetch(int $connectionIdx): void
	{
		$this->steps[] = [$connectionIdx, '', '...SUCCESS'];
		$this->successfulStepsCount++;
	}


	public function addFailedResultFetch(int $connectionIdx): void
	{
		$this->steps[] = [$connectionIdx, '', '...FAILURE'];
	}


	public function addException(QueryException $exception): void
	{
		assert($this->exception === NULL);
		$this->exception = $exception;
	}


	public function build(): ScenarioExecutionResult
	{
		return new ScenarioExecutionResult($this->steps, $this->successfulStepsCount, $this->exception);
	}
}
