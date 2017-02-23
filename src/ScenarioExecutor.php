<?php declare(strict_types = 1);

namespace Mangoweb\PgDeadlockPlayground;


class ScenarioExecutor
{
	/** @var ConnectionPoolFactory */
	private $connectionPoolFactory;


	public function __construct(ConnectionPoolFactory $connectionPoolFactory)
	{
		$this->connectionPoolFactory = $connectionPoolFactory;
	}


	public function execute(Scenario $scenario): ScenarioExecutionResult
	{
		$pool = $this->connectionPoolFactory->create();
		$process = new ScenarioExecutionProcess($pool, $scenario);
		$result = $process->run();

		return $result;
	}
}
