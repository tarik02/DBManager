<?php
namespace GoldWill\DBManager\util;

use GoldWill\DBManager\ConnectionConfig;
use GoldWill\DBManager\query\Query;
use GoldWill\DBManager\query\QueryResult;


class QueryUtil
{
	/**
	 * @param ConnectionConfig $connectionConfig
	 * @param Query $query
	 *
	 * @return QueryResult|QueryResult[]
	 */
	public static function query(ConnectionConfig $connectionConfig, Query $query)
	{
		return $query->query($connectionConfig);
	}
}