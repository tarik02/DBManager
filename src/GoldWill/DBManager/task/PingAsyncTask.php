<?php
namespace GoldWill\DBManager\task;

use GoldWill\DBManager\DBManager;
use pocketmine\scheduler\PluginTask;


class PingAsyncTask extends PluginTask
{
	/** @var DBManager */
	protected $owner;
	
	/**
	 * PingAsyncTask constructor.
	 *
	 * @param DBManager $owner
	 */
	public function __construct(DBManager $owner)
	{
		parent::__construct($owner);
	}
	
	public function onRun($currentTick)
	{
		$this->owner->pingAsync();
	}
}