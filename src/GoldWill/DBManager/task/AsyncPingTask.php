<?php
namespace GoldWill\DBManager\task;

use GoldWill\DBManager\ConnectionConfig;
use GoldWill\DBManager\DBManager;


class AsyncPingTask extends AsyncTask
{
	public function __construct(DBManager $owner, ConnectionConfig $connectionConfig)
	{
		parent::__construct($owner, $connectionConfig);
	}
	
	public function handleRun()
	{
		$this->connectionConfig->getConnection();
	}
	
	public function handleCompetition(DBManager $DBManager)
	{
		
	}
}