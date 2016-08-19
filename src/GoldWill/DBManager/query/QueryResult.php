<?php
namespace GoldWill\DBManager\query;

class QueryResult
{
	/** @var QueryResultRow[] */
	private $rows;
	
	/** @var int|null */
	private $insertId;
	
	/** @var string */
	private $query;
	
	/** @var string|null */
	private $error;
	
	
	/**
	 * QueryResult constructor
	 * .
	 * @param QueryResultRow[] $rows
	 * @param int|null $insertId
	 * @param string $query
	 * @param string|null $error
	 */
	public function __construct(array $rows, int $insertId = null, string $query, string $error = null)
	{
		$this->rows = $rows;
		$this->insertId = $insertId;
		$this->query = $query;
		$this->error = $error;
	}
	
	/**
	 * @return QueryResultRow|null
	 */
	public function fetch()
	{
		return array_shift($this->rows);
	}
	
	/**
	 * @return QueryResultRow[]
	 */
	public function fetchAll()
	{
		$rows = $this->rows;
		$this->rows = [  ];
		
		return $rows;
	}
	
	/**
	 * @return int|null
	 */
	public function getInsertId()
	{
		return $this->insertId;
	}
	
	/**
	 * @return string
	 */
	public function getQuery() : string
	{
		return $this->query;
	}
	
	/**
	 * @return string
	 */
	public function getError() : string
	{
		return $this->error;
	}
	
	/**
	 * @return bool
	 */
	public function hasError() : bool
	{
		return $this->error !== null;
	}
}