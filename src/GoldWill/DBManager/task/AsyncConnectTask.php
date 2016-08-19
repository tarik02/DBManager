<?php
namespace GoldWill\DBManager\task;

use GoldWill\DBManager\ConnectionConfig;
use GoldWill\DBManager\DBManager;


class AsyncConnectTask extends AsyncTask
{
	/** @var string|null */
	private $error;
	
	public function __construct(DBManager $DBManager, ConnectionConfig $connectionConfig)
	{
		parent::__construct($DBManager, $connectionConfig);
	}
	
	protected function handleRun()
	{
		try
		{
			$this->connectionConfig->getConnection();
		}
		catch (\Exception $e)
		{
			$this->error = $e->getMessage();
		}
	}
	
	protected function handleCompetition(DBManager $DBManager)
	{
		if ($this->error === null)
		{
			$DBManager->getLogger()->notice('Async connection created.');
		}
		else
		{
			$DBManager->getLogger()->error($this->error);
		}
	}
}