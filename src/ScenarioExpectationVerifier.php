<?php declare(strict_types = 1);

namespace Mangoweb\PgDeadlockPlayground;

use Tester;


class ScenarioExpectationVerifier
{
	/** @var ScenarioExecutor */
	private $executor;

	/** @var bool */
	private $isVerbose = FALSE;


	public function __construct(ScenarioExecutor $executor)
	{
		$this->executor = $executor;
	}


	public function setVerbose(bool $isVerbose = TRUE): void
	{
		$this->isVerbose = $isVerbose;
	}


	public function expectError(string $expectedError, Scenario $scenario): bool
	{
		$result = $this->executor->execute($scenario);
		$actualError = $result->getError();

		if ($actualError === NULL) {
			$this->printFailure($scenario, $result, "expected error '$expectedError', but was OK");
			return FALSE;

		} elseif (!Tester\Assert::isMatching($expectedError, $actualError)) {
			$this->printFailure($scenario, $result, "expected error '$expectedError', but got '$actualError'");
			return FALSE;

		} else {
			$this->printSuccess($scenario, $result, "failed with expected error '$actualError'");
			return TRUE;
		}
	}


	public function expectOk(Scenario $scenario): bool
	{
		$result = $this->executor->execute($scenario);
		$actualError = $result->getError();

		if ($actualError === NULL) {
			$this->printSuccess($scenario, $result, 'completed without error');
			return TRUE;

		} else {
			$this->printFailure($scenario, $result, "no error was expected, but got '$actualError'");
			return FALSE;
		}
	}


	public function expectAlwaysOk(Scenario $scenario, bool $stopOnFailure = TRUE): bool
	{
		$result = TRUE;

		foreach ($scenario->getAllOrderings() as $scenarioVariant) {
			$result = $result && $this->expectOk($scenarioVariant);
			if ($stopOnFailure && !$result) {
				break;
			}
		}

		return $result;
	}


	private function printSuccess(Scenario $scenario, ScenarioExecutionResult $result, string $message): void
	{
		if ($this->isVerbose) {
			$this->printHeader("SUCCESS: $message");
			$result->dump();
		}
	}


	private function printFailure(Scenario $scenario, ScenarioExecutionResult $result, string $message): void
	{
		$this->printHeader("FAILURE: $message");
		$result->dump();
	}


	private function printHeader(string $line): void
	{
		$trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
		$frame = end($trace);

		echo "\n";
		echo "$line\n";
		echo "in $frame[file]:$frame[line]\n\n";
	}
}
