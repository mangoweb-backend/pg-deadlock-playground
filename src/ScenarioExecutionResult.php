<?php declare(strict_types = 1);

namespace Mangoweb\PgDeadlockPlayground;

use Nette\Utils\Strings;


class ScenarioExecutionResult
{
	/** @var array */
	private $steps;

	/** @var int */
	private $successfulStepsCount;

	/** @var NULL|QueryException */
	private $exception;


	public function __construct(array $steps, int $successfulStepsCount, ?QueryException $exception)
	{
		$this->steps = $steps;
		$this->successfulStepsCount = $successfulStepsCount;
		$this->exception = $exception;
	}


	public function getSteps(): array
	{
		return $this->steps;
	}


	public function getError(): ?string
	{
		return $this->exception ? $this->normalizeError($this->exception->getMessage()) : NULL;
	}


	public function dump(): void
	{
		$groupedCells = $this->getCellsGroupedByConnection();
		$columnPaddings = [];
		$columnWidths = [];

		ksort($groupedCells);
		foreach ($groupedCells as $connectionIdx => $cells) {
			$columnPaddings[$connectionIdx] = end($columnPaddings) + end($columnWidths);
			$columnWidths[$connectionIdx] = 3 + array_reduce($cells, function (?int $maxLength, string $cell) {
				return max($maxLength ?? 0, Strings::length($cell));
			});
		}

		foreach ($this->steps as $stepIdx => list($connectionIdx, $query, $tag)) {
			echo ($stepIdx === $this->successfulStepsCount) ? '-> ' : '   ';
			echo str_repeat(' ', $columnPaddings[$connectionIdx]);
			echo $this->formatCell($query, $tag);
			echo "\n";
		}

		echo str_repeat('-', array_sum($columnWidths)), "\n";
	}


	private function getCellsGroupedByConnection(): array
	{
		$result = [];
		foreach ($this->steps as [$connectionIdx, $query, $tag]) {
			$result[$connectionIdx][] = $this->formatCell($query, $tag);
		}

		return $result;
	}


	private function formatCell(string $query, string $tag): string
	{
		return sprintf('%-12s %s', $tag ? "[$tag]" : '', $query);
	}


	private function normalizeError(string $error): string
	{
		return Strings::after(Strings::trim(Strings::replace($error, '#\s++#', ' ')), 'ERROR: ');
	}
}
