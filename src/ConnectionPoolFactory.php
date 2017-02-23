<?php declare(strict_types = 1);

namespace Mangoweb\PgDeadlockPlayground;


class ConnectionPoolFactory
{
	/** @var ConnectionFactory */
	private $connectionFactory;

	/** @var string[] */
	private $initQueries;


	public function __construct(ConnectionFactory $connectionFactory, array $initQueries = [])
	{
		$this->connectionFactory = $connectionFactory;
		$this->initQueries = $initQueries;
	}


	public function create(): ConnectionPool
	{
		$pool = new ConnectionPool($this->connectionFactory);

		foreach ($this->initQueries as $query) {
			$result = pg_query($pool->get(-1), $query);
			assert($result !== FALSE);
		}

		return $pool;
	}
}

