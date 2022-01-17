<?php

namespace App;

use Monolog\Logger;
use Monolog\Handler\StreamHandler;

class Query
{
    public static $dbLogger = null;

    /**
     * @var string
     */
    private $queryString;

    /**
     * @var array
     */
    private $parameters = array();

    /**
     * Query result
     * @var
     */
    private $result;

    /**
     * If query executed or not
     * @var bool
     */
    private $executed = false;

    /**
     * @param $query
     */
    public function __construct($pdo, $query)
    {
        $this->pdo = $pdo;
        $this->queryString = $query;

        if (!self::$dbLogger) {
            global $loggingStream;

            self::$dbLogger = new Logger('db');
            self::$dbLogger->pushHandler($loggingStream);
        }
    }

    public function __destruct()
    {
        if (!$this->executed) {
            self::$dbLogger->error("Defined but not exec'd: " . $this->queryString);
        }
    }

    public function exec()
    {
        if ($this->executed) {
            self::$dbLogger->warning("Query exec'd already.");
            return false;
        }

        $this->executed = true;

        $query = $this->queryString;
        $st = null;

        try {
            if (!empty($this->parameters)) {
                $st = $this->pdo->prepare($query);
                $st->execute($this->parameters);
            } else {
                $st = $this->pdo->query($query);
            }
        } catch (\PDOException $e) {
            self::$dbLogger->error(sprintf("Query exec failed: %s", $query));
            throw $e; // Rethrow, we can't let this slide
        }

        $this->result = $st;
        return $st;
    }

    public function execWith($params)
    {
        if ($this->bind($params)) {
            return $this->exec();
        }

        return null;
    }

    /**
     * Adds query parameters
     * @param $key string|array
     * @param $value string
     * @return bool
     */
    public function bind($key, $value = null)
    {
        if (!is_array($key)) {
            if (isset($this->parameters[$key])) {
                self::$dbLogger->warning("binding previously defined already [$key - $value]");
                return false;
            }

            $this->parameters[$key] = $value;
        } else {
            if ($value !== null) {
                self::$dbLogger->warning("bind is an array but value is not null. value is ignorado on these cases."); //non-fatal
            }

            foreach ($key as $k => $v) {
                $this->bind($k, $v);
            }
        }
        return true;
    }

    /**
     * Gets last inserted id
     * @return int
     */
    public static function getLastInsertId()
    {
        return $this->pdo->lastInsertID();
    }

    /**
     * Executes a simple query of which we need no data.
     *
     * @param string $query The query to execute
     * @param array $bindings The bindings to assign
     * @return bool
     */
    public static function run($pdo, $query, $bindings = [])
    {
        return (new Query($pdo, $query))->execWith($bindings);
    }

    /**
     * Executes a query with bindings and returns a fetched array of data
     *
     * @param string $query The query to execute
     * @param array $bindings The bindings to assign
     * @return array|null
     */
    public static function fetchOne($pdo, $query, $bindings = [])
    {
        $q = new Query($pdo, $query);
        return $q->execWith($bindings) ? $q->fetch() : null;
    }
}
