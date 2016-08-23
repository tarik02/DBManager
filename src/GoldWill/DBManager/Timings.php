<?php
namespace GoldWill\DBManager;

use pocketmine\event\TimingsHandler;


class Timings
{
	/** @var TimingsHandler */
	public static $asyncComplete;
	
	/** @var TimingsHandler */
	public static $asyncQueryComplete;
	
	
	public static function init()
	{
		if (self::$asyncComplete instanceof TimingsHandler)
		{
			return;
		}
		
		self::$asyncComplete = new TimingsHandler('** Async complete');
		self::$asyncQueryComplete = new TimingsHandler('** Async query complete');
	}
}