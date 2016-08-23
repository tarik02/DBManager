<?php
namespace GoldWill\DBManager\query;

class QueryResultRow implements \ArrayAccess
{
	const TYPE_AUTO = -1;
	const TYPE_INT = 0; const TYPE_INTEGER = 0;
	const TYPE_FLOAT = 1; const TYPE_DOUBLE = 1;
	const TYPE_STRING = 2;
	const TYPE_JSON = 3;
	
	
	/** @var array */
	private $columns;
	
	
	public function __construct(array $columns)
	{
		$this->columns = $columns;
	}
	
	/**
	 * @param string $offset
	 * @param int $type
	 *
	 * @return mixed
	 */
	public function get(string $offset, int $type = self::TYPE_AUTO)
	{
		$value = (is_integer($offset) && ($offset >= 0) && ($offset < count($this->columns))) ? (array_values($this->columns)[$offset]) : ((isset($this->columns[$offset])) ? ($this->columns[$offset]) : (null));
		
		switch ($type)
		{
		case self::TYPE_AUTO:
			if ($value === 'NULL')
			{
				return null;
			}
			
			if (ctype_digit(strval($value)))
			{
				return intval($value);
			}
			
			if (is_numeric($value))
			{
				return floatval($value);
			}
			
			try
			{
				$result = json_decode($value, true);
				
				if ($result === null)
				{
					if ($value === 'NULL')
					{
						return null;
					}
				}
				else
				{
					return $result;
				}
			}
			catch (\Exception $e)
			{
				
			}
			
			return $value;
		case self::TYPE_INT:
			return intval($value);
		case self::TYPE_FLOAT:
			return floatval($value);
		case self::TYPE_STRING:
			return $value;
		case self::TYPE_JSON:
			return json_decode($value, true);
		default:
			return $value;
		}
	}
	
	/**
	 * Whether a offset exists
	 * @link http://php.net/manual/en/arrayaccess.offsetexists.php
	 * @param mixed $offset <p>
	 * An offset to check for.
	 * </p>
	 * @return boolean true on success or false on failure.
	 * </p>
	 * <p>
	 * The return value will be casted to boolean if non-boolean was returned.
	 * @since 5.0.0
	 */
	public function offsetExists($offset)
	{
		return (isset($this->columns[$offset])) || ((is_integer($offset) && ($offset >= 0) && ($offset < count($this->columns))));
	}
	
	/**
	 * Offset to retrieve
	 * @link http://php.net/manual/en/arrayaccess.offsetget.php
	 * @param mixed $offset <p>
	 * The offset to retrieve.
	 * </p>
	 * @return mixed Can return all value types.
	 * @since 5.0.0
	 */
	public function offsetGet($offset)
	{
		return $this->get($offset, self::TYPE_AUTO);
	}
	
	/**
	 * Offset to set
	 * @link http://php.net/manual/en/arrayaccess.offsetset.php
	 * @param mixed $offset <p>
	 * The offset to assign the value to.
	 * </p>
	 * @param mixed $value <p>
	 * The value to set.
	 * </p>
	 * @return void
	 * @since 5.0.0
	 */
	public function offsetSet($offset, $value)
	{
		throw new \RuntimeException('Cannot set value in QueryResultRow');
	}
	
	/**
	 * Offset to unset
	 * @link http://php.net/manual/en/arrayaccess.offsetunset.php
	 * @param mixed $offset <p>
	 * The offset to unset.
	 * </p>
	 * @return void
	 * @since 5.0.0
	 */
	public function offsetUnset($offset)
	{
		throw new \RuntimeException('Cannot unset value in QueryResultRow');
	}
	
	/**
	 * @return array
	 */
	public function toArray() : array
	{
		return $this->columns;
	}
}