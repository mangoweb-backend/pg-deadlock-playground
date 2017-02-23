<?php declare(strict_types = 1);

namespace Mangoweb\PgDeadlockPlayground;

use Nette\Utils\Strings;
use Traversable;


class Scenario
{
	/** @var array */
	private $steps;


	public static function fromArray(array $steps): self
	{
		$scenario = new self();

		foreach ($steps as $step) {
			foreach ($step as $connectionIdx => $query) {
				if ($query !== NULL) {
					$scenario->addStep($connectionIdx, $query);
				}
			}
		}

		return $scenario;
	}


	public function addStep(int $connectionIdx, string $query): void
	{
		$this->steps[] = [$connectionIdx, $query];
	}


	public function getSteps(): array
	{
		return $this->steps;
	}


	public function dump(): void
	{
		$groupedQueries = $this->getQueriesGroupedByConnection();
		$columnPaddings = [];
		$columnWidths = [];

		foreach ($groupedQueries as $connectionIdx => $queries) {
			$columnPaddings[$connectionIdx] = end($columnPaddings) + end($columnWidths);
			$columnWidths[$connectionIdx] = 3 + array_reduce($queries, function (?int $maxLength, string $query) {
				return max($maxLength ?? 0, Strings::length($query));
			});
		}

		foreach ($this->steps as $stepIdx => list($connectionIdx, $query)) {
			echo str_repeat(' ', $columnPaddings[$connectionIdx]);
			echo $query;
			echo "\n";
		}

		echo str_repeat('-', array_sum($columnWidths) - 3), "\n";
	}


	/**
	 * @return Traversable|Scenario[]
	 */
	public function getAllOrderings(): Traversable
	{
		yield from $this->generateCombinations(
			$this->getQueriesGroupedByConnection(),
			new Scenario()
		);
	}


	private function generateCombinations(array $groupedQueries, Scenario $baseScenario): Traversable
	{
		if (count($groupedQueries) === 0) {
			yield $baseScenario;
		}

		foreach ($groupedQueries as $connectionIdx => $_) {
			$newGroupedQueries = $groupedQueries;
			$newQuery = array_shift($newGroupedQueries[$connectionIdx]);

			if (count($newGroupedQueries[$connectionIdx]) === 0) {
				unset($newGroupedQueries[$connectionIdx]);
			}

			$newScenario = clone $baseScenario;
			$newScenario->addStep($connectionIdx, $newQuery);
			yield from $this->generateCombinations($newGroupedQueries, $newScenario);
		}
	}


	private function getQueriesGroupedByConnection(): array
	{
		$result = [];
		foreach ($this->steps as [$connectionIdx, $query]) {
			$result[$connectionIdx][] = $query;
		}

		return $result;
	}
}
