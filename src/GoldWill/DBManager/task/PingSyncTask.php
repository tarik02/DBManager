<?php
namespace GoldWill\DBManager\task;

use GoldWill\DBManager\DBManager;
use pocketmine\scheduler\PluginTask;


class PingSyncTask extends PluginTask
{
	/** @var DBManager */
	protected $owner;
	
	/**
	 * PingSyncTask constructor.
	 *
	 * @param DBManager $owner
	 */
	public function __construct(DBManager $owner)
	{
		parent::__construct($owner);
	}
	
	public function onRun($currentTick)
	{
		$this->owner->pingSync();
	}
}