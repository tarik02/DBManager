<?php
namespace GoldWill\DBManager;

class ConnectionConfig
{
	/** @var \mysqli|null */
	private static $connection = null;
	
	/** @var string */
	private $host;
	
	/** @var int */
	private $port;
	
	/** @var string */
	private $username;
	
	/** @var string */
	private $password;
	
	/** @var string */
	private $database;
	
	
	/**
	 * ConnectionConfig constructor.
	 *
	 * @param array $config
	 */
	public function __construct(array $config)
	{
		$this->host = @$config['host'] ?: 'localhost';
		$this->port = (isset($config['port'])) ? (intval($config['port'])) : 3306;
		$this->username = @$config['username'] ?: 'root';
		$this->password = @$config['password'] ?: '';
		$this->database = @$config['database'] ?: 'mcpe';
	}
	
	
	/**
	 * @return \mysqli|null
	 */
	public function getConnection()
	{
		if ((self::$connection === null) || (!@self::$connection->ping()))
		{
			$connection = @new \mysqli($this->host, $this->username, $this->password, $this->database, $this->port);
			
			if ($connection->connect_error)
			{
				throw new \RuntimeException($connection->connect_error);
			}
			else
			{
				self::$connection = $connection;
				
				return $connection;
			}
		}
		
		return self::$connection;
	}
}