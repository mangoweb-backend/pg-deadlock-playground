<?php declare(strict_types = 1);

namespace Mangoweb\PgDeadlockPlayground;


class ConnectionFactory
{
	/** @var array */
	private $config;


	public function __construct(array $config)
	{
		$this->config = $config;
	}


	public function create()
	{
		$connection = pg_connect($this->buildConnectionString(), PGSQL_CONNECT_FORCE_NEW);
		assert($connection !== FALSE);

		return $connection;
	}


	private function buildConnectionString(): string
	{
		static $knownKeys = [
			'host', 'hostaddr', 'port', 'dbname', 'user', 'password',
			'connect_timeout', 'options', 'sslmode', 'service',
		];

		$connectionString = '';
		foreach ($knownKeys as $key) {
			if (isset($this->config[$key])) {
				$connectionString .= $key . '=' . $this->config[$key] . ' ';
			}
		}

		return $connectionString;
	}
}

