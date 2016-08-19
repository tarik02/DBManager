<?php
namespace GoldWill\DBManager\query;

class AsyncQueryData
{
	/** @var Query */
	private $query;
	
	/** @var callable|null */
	private $callback;
	
	
	/**
	 * AsyncQueryData constructor.
	 *
	 * @param Query $query
	 * @param callable|null $callback
	 */
	public function __construct(Query $query, callable $callback = null)
	{
		$this->query = $query;
		$this->callback = $callback;
	}
	
	/**
	 * @return Query
	 */
	public function getQuery() : Query
	{
		return $this->query;
	}
	
	/**
	 * @return callable|null
	 */
	public function getCallback()
	{
		return $this->callback;
	}
}