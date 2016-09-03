<?php
namespace GoldWill\DBManager;

use GoldWill\DBManager\query\AsyncQueryData;
use GoldWill\DBManager\query\Query;
use GoldWill\DBManager\query\QueryResult;
use GoldWill\DBManager\task\AsyncConnectTask;
use GoldWill\DBManager\task\AsyncPingTask;
use GoldWill\DBManager\task\AsyncQueryTask;
use GoldWill\DBManager\task\AsyncTask;
use GoldWill\DBManager\task\PingAsyncTask;
use GoldWill\DBManager\task\PingSyncTask;
use GoldWill\DBManager\task\UpdateTask;
use GoldWill\DBManager\util\ErrorUtil;
use GoldWill\DBManager\util\QueryUtil;
use pocketmine\event\Listener;
use pocketmine\plugin\PluginBase;
use pocketmine\scheduler\AsyncPool;
use pocketmine\Server;


class DBManager extends PluginBase implements Listener
{
	/** @var ConnectionConfig */
	private $connectionConfig;
	

	/** @var bool */
	private $syncPingEnable;
	
	/** @var int */
	private $syncPingInterval;
	
	
	/** @var bool */
	private $asyncEnable;
	
	/** @var int */
	private $asyncWorkers;
	
	/** @var bool */
	private $asyncPingEnable;
	
	/** @var int */
	private $asyncPingInterval;
	
	
	/** @var bool */
	private $errorLogEnable;
	
	/** @var string */
	private $errorLogTable;
	
	/** @var string */
	private $errorLogCreateQuery;
	
	/** @var string */
	private $errorLogInsertQuery;
	
	
	/** @var AsyncPool */
	private $asyncQueryPool;
	
	/** @var AsyncQueryData[] */
	private $asyncQueries = [  ];
	
	/** @var int */
	private $asyncQueryCounter = 0;
	
	private $asyncQueryTaskCounter = 0;
	
	
	public function onLoad()
	{
		Timings::init();
		
		$this->readConfig();
	}
	
	public function onEnable()
	{
		$this->setup();
		
		
		$this->queryAsync('TURCATE TABLE `testTable`');
	}
	
	public function onDisable()
	{
		
	}
	
	private function readConfig()
	{
		$this->saveDefaultConfig();
		$this->reloadConfig();
		$this->saveResource('errorLog_createTable.sql');
		$this->saveResource('errorLog_insertError.sql');
		
		$config = $this->getConfig();
		
		$this->connectionConfig = new ConnectionConfig($config->get('mysql'));
		
		$this->syncPingEnable = $config->getNested('sync.ping.enable', false);
		$this->syncPingInterval = $config->getNested('sync.ping.interval', 1200);
		
		$this->asyncEnable = $config->getNested('async.enable', true);
		$this->asyncWorkers = $config->getNested('async.workers', 1);
		$this->asyncPingEnable = $config->getNested('async.ping.enable', true);
		$this->asyncPingInterval = $config->getNested('async.ping.interval', 1200);
		
		$this->errorLogEnable = $config->getNested('errorLog.enable', true);
		$this->errorLogTable = $config->getNested('errorLog.table', 'dbmanager_errors');
		
		$fileHandle = $this->getResource('errorLog_createTable.sql');
		$this->errorLogCreateQuery = stream_get_contents($fileHandle);
		fclose($fileHandle);
		
		$fileHandle = $this->getResource('errorLog_insertError.sql');
		$this->errorLogInsertQuery = stream_get_contents($fileHandle);
		fclose($fileHandle);
	}
	
	private function setup()
	{
		$this->getLogger()->info('Setup...');
		$this->getLogger()->info('Starting sync connection...');
		
		try
		{
			$this->connectionConfig->getConnection();
		}
		catch (\Exception $e)
		{
			$this->getLogger()->error('Connecting error: ' . $e->getMessage() . '.');
			
			return;
		}
		
		$this->getLogger()->info('Sync connection created.');
		
		if ($this->syncPingEnable)
		{
			$this->getLogger()->info('Scheduling sync ping timer...');
			
			$this->getServer()->getScheduler()->scheduleDelayedRepeatingTask(new PingSyncTask($this), $this->syncPingInterval, $this->syncPingInterval);
		}
		
		
		if ($this->asyncEnable)
		{
			$this->getLogger()->info('Starting async connections...');
			
			$this->asyncQueryPool = new AsyncPool($this->getServer(), $this->asyncWorkers);
			
			for ($i = $this->asyncWorkers - 1; $i >= 0; --$i)
			{
				$this->scheduleAsyncTask(new AsyncConnectTask($this, $this->connectionConfig), $i);
			}
			
			$this->getServer()->getScheduler()->scheduleRepeatingTask(new UpdateTask($this), 1);
			
			if ($this->asyncPingEnable)
			{
				$this->getLogger()->info('Scheduling async ping timer...');
				
				$this->getServer()->getScheduler()->scheduleDelayedRepeatingTask(new PingAsyncTask($this), $this->asyncPingInterval, $this->asyncPingInterval);
			}
		}
		
		
		if ($this->errorLogEnable)
		{
			$query = str_replace('{table_name}', $this->errorLogTable, $this->errorLogCreateQuery);
			
			try
			{
				$this->querySync($query);
			}
			catch (\Exception $e)
			{
				$this->getLogger()->logException($e);
			}
		}
	}
	
	public function pingSync()
	{
		$this->connectionConfig->getConnection();
	}
	
	public function pingAsync()
	{
		for ($i = $this->asyncWorkers - 1; $i >= 0; --$i)
		{
			$this->scheduleAsyncTask(new AsyncPingTask($this, $this->connectionConfig), $i);
		}
	}
	
	public function update()
	{
		$this->asyncQueryPool->collectTasks();
	}
	
	/**
	 * @param int $queryId
	 * @param QueryResult|QueryResult[] $result
	 */
	public function handleQueryDone(int $queryId, $result)
	{
		if (!isset($this->asyncQueries[$queryId]))
		{
			return;
		}
		
		$query = $this->asyncQueries[$queryId];
		unset($this->asyncQueries[$queryId]);
		
		
		try
		{
			$callback = $query->getCallback();
			
			if (is_callable($callback))
			{
				/** @var QueryResult[] $results */
				$results = (is_array($result)) ? ($result) : ([$result]);
				
				foreach ($results as $result)
				{
					if ($result->hasError())
					{
						$this->getLogger()->info('Error ' . $result->getError() . ' happened in query: ' . $result->getQuery());
					}
				}
				
				call_user_func_array($callback, $results);
			}
		}
		catch (\Exception $e)
		{
			$this->getLogger()->error('Error when handling query ' . PHP_EOL . $query->getQuery()->toString($this->connectionConfig) . ': ' . $e->__toString() . '.');
		}
	}
	
	/**
	 * @param int $queryId
	 * @param \Exception $e
	 */
	public function handleQueryException(int $queryId, \Exception $e)
	{
		if (!isset($this->asyncQueries[$queryId]))
		{
			return;
		}
		
		$query = $this->asyncQueries[$queryId]->getQuery();
		unset($this->asyncQueries[$queryId]);
		
		$this->logError($e, $query);
	}
	
	/**
	 * @param \Exception $e
	 *
	 * @param Query $query
	 */
	private function logError(\Exception $e, Query $query)
	{
		if ($this->errorLogEnable)
		{
			$insertQuery = str_replace('{table_name}', $this->errorLogTable, $this->errorLogInsertQuery);
			
			$this->queryAsync($insertQuery, [
				'query' => $query->toString($this->connectionConfig),
			    'exception' => ErrorUtil::errorToString($e)
			]);
		}
		
		$this->getLogger()->error('Error in query: ' . $query->toString($this->connectionConfig));
		$this->getLogger()->logException($e);
	}
	
	/**
	 * @param AsyncTask $task
	 * @param int $worker
	 */
	private function scheduleAsyncTask(AsyncTask $task, int $worker = -1)
	{
		$task->setTaskId($this->asyncQueryTaskCounter);
		++$this->asyncQueryTaskCounter;
		
		if ($worker === -1)
		{
			$this->asyncQueryPool->submitTask($task);
		}
		else
		{
			$this->asyncQueryPool->submitTaskToWorker($task, $worker);
		}
	}
	
	
	/*
	 * API part
	 */
	
	/**
	 * @param string|string[] $query
	 * @param array|null $parameters
	 *
	 * @return QueryResult|QueryResult[]
	 */
	public function querySync($query, array $parameters = null)
	{
		$queryObject = new Query((is_array($query)) ? ($query) : ([$query]), $parameters);
		
		try
		{
			return QueryUtil::query($this->connectionConfig, $queryObject);
		}
		catch (\Exception $e)
		{
			$this->logError($e, $queryObject);
			
			return [  ];
		}
	}
	
	/**
	 * @param string|string[] $query
	 * @param array|null $parameters
	 * @param callable|null $callback
	 */
	public function queryAsync($query, array $parameters = null, callable $callback = null)
	{
		if ($this->asyncEnable)
		{
			$queryId = $this->asyncQueryCounter;
			++$this->asyncQueryCounter;
			
			$queryObject = new Query((is_array($query)) ? ($query) : ([ $query ]), $parameters);
			
			$this->asyncQueries[$queryId] = new AsyncQueryData($queryObject, $callback);
			$this->scheduleAsyncTask(new AsyncQueryTask($this, $this->connectionConfig, $queryId, $queryObject));
		}
		else
		{
			$results = $this->querySync($query, $parameters);
			
			if (!is_array($results))
			{
				$results = [ $results ];
			}
			
			if (is_callable($callback))
			{
				call_user_func_array($callback, $results);
			}
		}
	}
	
	/**
	 * @param Server|null $server
	 *
	 * @return DBManager
	 */
	public static function getInstance(Server $server = null) : DBManager
	{
		if ($server === null)
		{
			return self::getInstance(Server::getInstance());
		}
		else
		{
			return $server->getPluginManager()->getPlugin('DBManager');
		}
	}
}