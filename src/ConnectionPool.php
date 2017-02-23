<?php declare(strict_types = 1);

namespace Mangoweb\PgDeadlockPlayground;


class ConnectionPool
{
	/** @var ConnectionFactory */
	private $connectionFactory;

	/** @var resource[] */
	private $pool = [];


	public function __construct(ConnectionFactory $connectionFactory)
	{
		$this->connectionFactory = $connectionFactory;
	}


	public function get(int $index)
	{
		if (!isset($this->pool[$index])) {
			$this->pool[$index] = $this->connectionFactory->create();
		}

		return $this->pool[$index];
	}


	public function destroy(): void
	{
		foreach ($this->pool as $connection) {
			pg_close($connection);
		}
	}
}
