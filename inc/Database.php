<?php

namespace App;

use App\Query;

/**
 * Database handler
 * @author: Think
 *
 * This class manages database connection, querying with PDO and automatic binding based on array values
 * and provides basic functions such as select, update, insert, delete...
 */
class Database
{
    private $pdo;
    private $local;
    private $svTables;
    private $dbName;
    private $conf;
    private $selectedServer;
    private $cacheData;
    private $cache;
    private $ignoreDBName = false;
    private $dbStack = [];


    public function __construct($dbData)
    {
        $this->conf = $dbData;
        $this->connect();
    }

    private function connect()
    {
        $pdo = new \PDO('mysql:host='.$this->conf['host'].';charset=utf8', $this->conf['user'], $this->conf['pass']);
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION); //Error handling: Exceptions
        $pdo->setAttribute(\PDO::ATTR_EMULATE_PREPARES, false); //It'd be pointless to set this all up to emulate them!
        $this->pdo = $pdo;
    }

    /**
     * Executes a simple query of which we need no data.
     *
     * @param string $query The query to execute
     * @param array $bindings The bindings to assign
     * @return bool
     */
    public function run($query, $bindings = [])
    {
        return Query::run($this->pdo, $query, $bindings);
    }

    /**
     * Executes a query with bindings and returns the query object data afterwards.
     * If the query failed, it will return null.
     *
     * @param string $query The query to execute
     * @param array $bindings The bindings to assign
     * @return Query|null
     */
    public function get($query, $bindings = [])
    {
        return Query::get($this->pdo, $query, $bindings);
    }

    /**
     * Executes a query with bindings and returns a fetched array of data
     *
     * @param string $query The query to execute
     * @param array $bindings The bindings to assign
     * @return array|null
     */
    public function fetchOne($query, $bindings = [])
    {
        return Query::fetchOne($this->pdo, $query, $bindings);
    }
}
