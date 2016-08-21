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
	
	
	public function onLoad()
	{
		$this->readConfig();
	}
	
	public function onEnable()
	{
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
			$callback = $query->getCallback();
			
			if (is_callable($callback))
			{
				$results = (is_array($result)) ? ($result) : ([$result]);
				
				call_user_func_array($callback, $results);
			}
		}
		catch (\Exception $e)
		{
			$this->getLogger()->error('Error when handling query ' . PHP_EOL . $query->getQuery()->__toString() . ': ' . $e->__toString() . '.');
		}
	}
	
	/**
	 * @param int $queryId
	 * @param \Exception $e
	 */
	public function handleQueryException(int $queryId, \Exception $e)
	{
		if (($query = @$this->asyncQueries[$queryId]) === null)
		{
			return;
		}
		
		unset($this->asyncQueries[$queryId]);
		
		
		try
		{
			//$callback = $query->getCallback();
			
			$this->getLogger()->error('Error in query' . PHP_EOL . $query->getQuery()->__toString() . ': ' . $e->__toString() . '.');
		}
		catch (\Exception $e)
		{
			$this->getLogger()->error('Error when handling query' . PHP_EOL . $query->getQuery()->__toString() . ': ' . $e->__toString() . '.');
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
	
	
	private $tests = [  ];
	
	private function testBegin($name, $count = 1)
	{
		$this->tests[$name] = [ microtime(true), $count ];
	}
	
	private function testEnd($name)
	{
		$test = $this->tests[$name];
		
		--$test[1];
		
		if ($test[1] !== 0)
		{
			$this->tests[$name] = $test;
			return;
		}
		
		unset($this->tests[$name]);
		
		$diff = microtime(true) - $test[0];
		
		
		$this->getLogger()->info('Test \'' . $name . '\' done in ' . round($diff, 3) . ' seconds.');
	}
	
	private function clearTests()
	{
		$this->tests = [  ];
		
		$this->queryAsync('TRUNCATE TABLE `benchmarking-table`');
	}
	
	private function test()
	{
		$this->clearTests();
		$this->testSync();
		
		$this->clearTests();
		$this->testAsync();
	}
	
	private function testSync()
	{
		$this->testBegin('Sync create 5000 entries', 5000);
		$this->testBegin('Sync create 5000 entries call');
		
		for ($i = 1; $i <= 5000; $i++)
		{
			$this->querySync('INSERT INTO `benchmarking-table`(`number`, `hash`) VALUES(@number, @hash)', [
				'number' => $i,
				'hash' => md5($i)
			]);
			
			$this->testEnd('Sync create 5000 entries');
		}
		
		$this->testEnd('Sync create 5000 entries call');
	}
	
	private function testAsync()
	{
		$this->testBegin('Async create 5000 entries', 5000);
		$this->testBegin('Async create 5000 entries call');
		
		for ($i = 1; $i <= 5000; $i++)
		{
			$this->queryAsync('INSERT INTO `benchmarking-table`(`number`, `hash`) VALUES(@number, @hash)', [
				'number' => $i,
				'hash' => md5($i)
			], function()
			{
				$this->testEnd('Async create 5000 entries');
			});
		}
		
		$this->testEnd('Async create 5000 entries call');
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
			
			if (is_callable($callback))
			{
				call_user_func_array($callback, $results);
			}
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