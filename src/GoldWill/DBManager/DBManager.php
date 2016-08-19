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
	
	
	/** @var AsyncPool */
	private $asyncQueryPool;
	
	/** @var AsyncQueryData[] */
	private $asyncQueries = [  ];
	
	/** @var int */
	private $asyncQueryCounter = 0;
	
	private $asyncQueryTaskCounter = 0;
	
	
	public function onEnable()
	{
		$this->readConfig();
		
		$this->setup();
		
		//$this->test();
	}
	
	public function onDisable()
	{
		
	}
	
	private function readConfig()
	{
		$this->saveDefaultConfig();
		$this->reloadConfig();
		
		$config = $this->getConfig();
		
		$this->connectionConfig = new ConnectionConfig($config->get('mysql'));
		
		$this->syncPingEnable = $config->getNested('sync.ping.enable', false);
		$this->syncPingInterval = $config->getNested('sync.ping.interval', 1200);
		
		$this->asyncEnable = $config->getNested('async.enable', true);
		$this->asyncWorkers = $config->getNested('async.workers', 1);
		$this->asyncPingEnable = $config->getNested('async.ping.enable', true);
		$this->asyncPingInterval = $config->getNested('async.ping.interval', 1200);
		
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
		if (($query = @$this->asyncQueries[$queryId]) === null)
		{
			return;
		}
		
		unset($this->asyncQueries[$queryId]);
		
		
		try
		{
			$results = (is_array($result)) ? ($result) : ([ $result ]);
			
			call_user_func_array($query->getCallback(), $results);
		}
		catch (\Exception $e)
		{
			$this->getLogger()->error('Error when handling query ' . PHP_EOL . $query->getQuery()->__toString() . ': ' . $e->__toString() . '.');
		}
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
	
	
	private function test()
	{
		for ($i = 0; $i < 10; $i++)
		{
			$this->queryAsync('SELECT @number', [
				'number' => $i
			], function(QueryResult $result)
			{
				//var_dump($result->fetch());
				$this->getLogger()->info($result->fetch()[0]);
			});
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
		return QueryUtil::query($this->connectionConfig, new Query((is_array($query)) ? ($query) : ([ $query ]), $parameters));
	}
	
	/**
	 * @param string|string[] $query
	 * @param array|null $parameters
	 * @param callable|null $callback
	 *
	 * @return QueryResult|QueryResult[]
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
			
			call_user_func_array($callback, $results);
		}
	}
	
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