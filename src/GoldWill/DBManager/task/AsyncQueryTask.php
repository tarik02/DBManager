<?php
namespace GoldWill\DBManager\task;

use GoldWill\DBManager\ConnectionConfig;
use GoldWill\DBManager\DBManager;
use GoldWill\DBManager\query\Query;
use GoldWill\DBManager\query\QueryResult;


class AsyncQueryTask extends AsyncTask
{
	/** @var int */
	private $queryId;
	
	/** @var Query|string */
	private $query;
	
	/** @var QueryResult|QueryResult[]|string|\Exception|null */
	private $result = null;
	
	
	/**
	 * AsyncPingTask constructor.
	 *
	 * @param DBManager $owner
	 * @param ConnectionConfig $connectionConfig
	 * @param int $queryId
	 * @param Query $query
	 */
	public function __construct(DBManager $owner, ConnectionConfig $connectionConfig, int $queryId, Query $query)
	{
		parent::__construct($owner, $connectionConfig);
		
		$this->queryId = $queryId;
		$this->query = serialize($query);
	}
	
	public function handleRun()
	{
		try
		{
			$this->query = unserialize($this->query);
			
			$this->result = serialize($this->query->query($this->connectionConfig));
		}
		catch (\Exception $e)
		{
			$this->result = serialize($e);
		}
		finally
		{
			$this->query = null;
		}
	}
	
	public function handleCompetition(DBManager $DBManager)
	{
		try
		{
			$result = unserialize($this->result);
			
			if ($this->result instanceof \Exception)
			{
				$DBManager->handleQueryException($this->queryId, $result);
			}
			else
			{
				$DBManager->handleQueryDone($this->queryId, $result);
			}
		}
		finally
		{
			$this->result = null;
		}
	}
}