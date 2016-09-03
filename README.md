# DBManager
A plugin for pocketmine that allows to use async mysql queries

## Features
 - Flexible config
 - Async queries
 - Can reconnect when lost connection(database server shutdown)
 - Can ping connection

## Config
- `mysql` - mysql connection config
	- `host` - address of mysql server(default `'localhost'`)
	- `port` - port of mysql server(default `3306`)
	- `username` - username to login to mysql server(default `'root'`)
	- `password` - password to login to mysql server(default empty)
	- `database` - database to use
- `sync` - syncronized connection config
	- `ping` - sync ping config
		- `enabled` - enables sync connection ping(default `false`)
		- `interval` - interval between ping, in seconds(default `1200`)
- `async` - asynchronized connections config
	- `enabled` - if false, will be used sync connection for async queries(default `true`)
	- `workers` - count of async connections(default `1`)
	- `ping` - async connections ping config
		- `enabled` - enables async connections ping(default `true`)
		- `interval` - interval between ping, in seconds(default `1200`)
- `errorLog` - error logging to database
	- `enable` - enabled error logging to database(default `true`)
	- `table` - table for error logging(default `dbmanager_errors`)

## API
You can do sync and async queries. I'm recommend to use always only ASYNC queries excepts plugins startup. SYNC queries block all server operations.

### Setup
1. Add field `$db` to your plugin:

		private $db;

2. Add to beginning of onEnable:

		$this->db = $this->getServer()->getPluginManager()->getPlugin('DBManager');


### Parameters
You can use parameters in your MySQL query:


	$this->db->querySync('SELECT @param1 AS `param1`, @param2 AS `param2`', [
		'param1' => 12,
		'param2' => 'Hello World'
	]);

will execute MySQL:

	SET @param1 = 12;
	SET @param2 = 'Hello World';
	SELECT @param1 AS `param1`, @param2 AS `param2`



### Sync queries
Sync query returns result(s) directly after execution and stops all the server while execution wil not be finished. You must to use this queries only in server startup. Examples:

	$result = $this->db->querySync('...QUERY...', [...PARAMETERS...]);

or return some results:

	list($result1, $result2) = $this->db->querySync([
		'...QUERY1...',
		'...QUERY2...
	], [...PARAMETERS...]);

Only SELECT, INSERT results will be returned from query.

### Async queries
Async queries return result(s) by callback. Async queries executes in background, so this not causes lags. Examples:

	$this->db->queryAsync('...QUERY...', [...PARAMETERS...], function($result) // Don't forget about use!
		{
			// Do something with $result
		});

or return some results:

	$this->db->queryAsync([
		'...QUERY1...',
		'...QUERY2...
	], [...PARAMETERS...], function($result1, $result2)
		{
			// Do something with $result1 and $result2
		});

### Result handling

#### Fetch single row
	$result->fetch() // Fetches next row from result or null

#### Fetch least rows
	$result->fetchAll() // Return array of rows from result

#### Get insert ID
	$result->getInsertId() // Returns last insert id or null

#### Get result query
	$result->getQuery() // Returns query from result(currently with bugs)

#### Get result error
	$result->getError() // Returns error from result or null

### Row handling
Column types:

 - `QueryResultRow::TYPE_AUTO` - automacally detects type of column
 - `QueryResultRow::TYPE_INT` or `QueryResultRow::TYPE_INTEGER` - means non floating point number
 - `QueryResultRow::TYPE_FLOAT` or `QueryResultRow::TYPE_DOUBLE` - means floating point number
 - `QueryResultRow::TYPE_STRING` - means string
 - `QueryResultRow::TYPE_JSON` - means json value(can be anything)


Get one column by name from row:

	use GoldWill\DBManager\query\QueryResultRow;
	
	...
	
	$row->get(name, type) // Returns column by name(or index), converts to given type(default auto)
	// or
	$row[name] // Returns column by name, converts to auto type

Convert row object to array:

	$row->toArray()
