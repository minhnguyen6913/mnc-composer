<?php

namespace Minhnhc\Database;

use App\Lib\Database\RecordSet;
use Exception;
use PDO;
use PDOStatement;
use Throwable;

class PDODatabase extends Database
{
    /**
     * PDO Options
     *
     * @var    array
     */
    public $options = array();

    protected $dsn;
    protected $hostname;
    protected $port;
    protected $username;
    protected $password;
    protected $database;
    private $subDriver;
    private bool $persistent = false;
    private bool $mysql = false;
    private bool $pgsql = false;
    private bool $sqlsrv = false;

    private int $pdoFetchType = PDO::FETCH_ASSOC;

    public function setFetchType(int $fetch_type)
    {
        parent::setFetchType($fetch_type);
        $this->pdoFetchType = PDO::FETCH_ASSOC;
        if ($this->getFetchType() ==  self::FETCH_TYPE_NUM) {
            $this->pdoFetchType = PDO::FETCH_NUM;
        }

    }

    /**
     * @var PDO
     */
    private $conn_id;
    private $trans_status = 0;

    // --------------------------------------------------------------------

    /**
     * Validates the DSN string and/or detects the subdriver.
     * PDODatabase constructor.
     * @param array $config
     * @throws Exception
     */
    public function __construct(array $config)
    {
        $this->uniqueId = uniqid("db-");
        $this->dsn = @$config['dsn'];
        $this->username = @$config['user'];
        $this->password = @$config['password'];
        $this->options = @$config['options'];

        if (isset($config['persistent']) && $config['persistent']) {
            $this->persistent = true;
        }

        if (preg_match('/([^:]+):\s*dbname\s*=\s*([A-Za-z0-9_]+)/i', $this->dsn, $match) && count($match) === 3) {
            // If there is a minimum valid dsn string pattern found, we're done
            // This is for general PDO users, who tend to have a full DSN string.
            $this->subDriver = $match[1];
            $this->schema = $match[2];
        } // Legacy support for DSN specified in the hostname field
        else if (preg_match('/([^:]+):/', $this->dsn, $match) && count($match) === 2) {
            // If there is a minimum valid dsn string pattern found, we're done
            // This is for general PDO users, who tend to have a full DSN string.
            $this->subDriver = $match[1];
        } // Legacy support for DSN specified in the hostname field

        elseif (preg_match('/([^:]+):/', $this->hostname, $match) && count($match) === 2) {
            $this->dsn = $this->hostname;
            $this->hostname = null;
            $this->subDriver = $match[1];
        } elseif (in_array($this->subDriver, array('mssql', 'sybase'), true)) {
            $this->subDriver = 'dblib';
            $this->dsn = null;
        } elseif ($this->subDriver === '4D') {
            $this->subDriver = '4d';
            $this->dsn = null;
        } elseif (!in_array($this->subDriver, array('4d', 'cubrid', 'dblib', 'firebird', 'ibm', 'informix', 'mysql', 'oci', 'odbc', 'pgsql', 'sqlite', 'sqlsrv'), TRUE)) {
            throw new Exception("Not support driver {$this->dsn}");
        }

        if ($this->subDriver == 'mysql') {
            $this->mysql = true;
        } elseif ($this->subDriver == 'pgsql') {
            $this->pgsql = true;
        }elseif ($this->subDriver == 'sqlsrv') {
            $this->sqlsrv = true;
        }

        if (!isset($this->options) || !is_array($this->options)) {
            if ($this->mysql) {
                $this->options = [
                    PDO::MYSQL_ATTR_INIT_COMMAND=>"SET NAMES utf8",
                    PDO::ATTR_EMULATE_PREPARES=>0
                ];
            }
        }

        $this->open($this->persistent);
    }

    /**
     * Database connection
     *
     * @param bool $persistent
     */
    private function open($persistent = false)
    {
        if ($persistent === true) {
            $this->options[PDO::ATTR_PERSISTENT] = true;
        }
        $this->conn_id = new PDO($this->dsn, $this->username, $this->password, $this->options);
        //self::$Pdo->exec("SET sql_mode=(SELECT REPLACE(@@sql_mode, 'ONLY_FULL_GROUP_BY', ''));");
        $this->conn_id->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        if ($this->mysql) {
            // Disable ONLY_FULL_GROUP_BY and allow NO_AUTO_VALUE_ON_ZERO
            // $this->conn_id->exec("SET sql_mode=(SELECT REPLACE(@@sql_mode, 'ONLY_FULL_GROUP_BY', ''));");
            $this->conn_id->exec("SET sql_mode='STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION,NO_AUTO_VALUE_ON_ZERO';");
            // 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'
        }
        // $this->conn_id->setAttribute(PDO::SQLSRV_ATTR_DIRECT_QUERY, true);
    }

    public function begin()
    {
        if ($this->trans_status != 0) {
            return;
        }
        $this->conn_id->beginTransaction();
        $this->trans_status = 1;
    }

    public function commit()
    {
        if ($this->trans_status != 1) {
            return;
        }
        $this->conn_id->commit();
        $this->trans_status = 0;
    }

    public function rollback()
    {
        if ($this->trans_status != 1) {
            return;
        }
        $this->conn_id->rollBack();
        $this->trans_status = 0;
    }

    public function close()
    {
        // No need to do
        if (isset($this->statement)) {
            try {
                $this->statement->closeCursor();
            }catch (Exception $e) {

            }
            $this->statement = null;
        }
    }

    /**
     * Insert ID
     *
     * @param string $name
     * @return ?int
     */
    public function insertedId($name = null): ?int
    {
        try {
            return $this->conn_id->lastInsertId($name);
        }catch (Exception $e) {
            return null;
        }
    }

    public function get(string $query, $params = [])
    {
        $fetchType = $this->getFetchType();
        $this->setFetchType(self::FETCH_TYPE_NUM);
        $rec = $this->getRecord($query, $params);
        $this->setFetchType($fetchType);
        if (isset($rec)) {
            return $rec[0];
        } else {
            return null;
        }
    }

    public function getRecord($query, $params = [])
    {
        $total = -1;
        $rs = $this->getRecordSet($query, $total, 0, 1, $params);
        if ($total > 0) {
            return $rs[0];
        } else {
            return null;
        }
    }

    /**
     * @param $query
     * @param int $total Number of row effected by this query. Set it to - 1 if dont want to get total of rows
     * @param int $start
     * @param int $page_size
     * @param array $params
     * @return array
     * @throws Exception
     */
    public function getRecordSet($query, &$total, $start = 0, $page_size = 0, $params = []): array
    {
        if ($total === -1 && $page_size === 1) {
            // For get function, just get only one record
            if ($this->mysql) {
                $query = "$query LIMIT 1";
            } elseif ($this->pgsql) {
                $query = "$query LIMIT 1";
            }
        }else{
            // Otherwise, use START, LIMIT for speed up
            if ($page_size > 0 || $start > 0) {
                $query = preg_replace('/\blimit\s[a-zA-Z0-9, ]+$/is', '', $query);
                if ($this->mysql) {
                    if ($page_size > 0) {
                        $end = $start + $page_size;
                        $query = "$query LIMIT $start, $end";
                    } else {
                        $query = "$query LIMIT $start";
                    }
                } elseif ($this->pgsql) {
                    if ($page_size > 0 && $start > 0) {
                        $query = "$query LIMIT $page_size OFFSET $start";
                    } else if ($page_size > 0) {
                        $query = "$query LIMIT $page_size";
                    } else if ($start > 0){
                        $query = "$query OFFSET $start";
                    }
                } elseif ($this->sqlsrv) {
                    if ($page_size > 0) {
                        $query .= " ORDER BY(SELECT NULL) OFFSET $start ROWS FETCH NEXT $page_size ROWS ONLY";
                    }else {
                        $query .= " ORDER BY(SELECT NULL) OFFSET $start ROWS FETCH NEXT 1000 ROWS ONLY";
                    }
                }
            }
        }

        // Remove limit if not select;
        if (!preg_match('/^\s*select.+$/is', $query)){
            $query = preg_replace('/limit [0-9].*$/is','', $query);
        }


        try {
            if ($start > 0 && !$this->mysql && !$this->pgsql && !$this->sqlsrv) {
                // Must use PDO::FETCH_ORI_REL to skip some record so should use PDO::CURSOR_SCROLL
                $stmt = $this->conn_id->prepare($query, [PDO::ATTR_CURSOR => PDO::CURSOR_SCROLL]);
            }else{
                // Forward for better performance
                $stmt = $this->conn_id->prepare($query, [PDO::ATTR_CURSOR => PDO::CURSOR_FWDONLY]);
            }
            $stmt->execute($params);
        } catch (Exception | Throwable $e) {
            throw new Exception($e->getMessage() . "\n$query");
        }
        $ret = [];
        $count = 0;

        // Get data
        $fetchType = PDO::FETCH_ASSOC;
        if ($this->getFetchType() == self::FETCH_TYPE_NUM) {
            $fetchType = PDO::FETCH_NUM;
        }

        // Use skip if database is not mysql nor pgsql, that dont support LIMIT, START
        if ($start > 0 && !$this->mysql && !$this->pgsql) {
            $row = $stmt->fetch($fetchType, PDO::FETCH_ORI_REL, $start);
            if (!$row) {
                if ($total !== -1) {
                    $total = $this->getTotal($query, $params);
                } else {
                    $total = 0;
                }
                return $ret;
            }
        }

        // Get data, until number of item equal page size.
        while ($row = $stmt->fetch($fetchType)) {
            $count++;
            $ret[] = $row;
            if ($page_size > 0 && $count >= $page_size) {
                break;
            }
        }

        if ($total !== -1) {
            if ($page_size <= 0) {
                $total = $start + $count;
            } else {
                $total = $this->getTotal($query, $params);
            }
        } else {
            // Dont need to know total
            $total = $count;
        }


        return $ret;
    }

    public function getTotal($query, $params = []): int
    {
        // Change to select count(*)
        $query = preg_replace('/^.+?\bfrom\b/is', 'SELECT 1' . ' FROM ', $query);

        // Remove order by
        $query = preg_replace('/\border\s+by\b[^()]+?$/is', '', $query);

        // Remove LIMIT
        $query = preg_replace('/\blimit\s[a-zA-Z0-9, ]+$/is', '', $query);

        $ret = $this->get("select count(*) from ($query) xxx", $params);
        if (!isset($ret)) {
            $ret = 0;
        }

        return $ret;
    }

    public function execute($query, $params = [])
    {
        try {
            $this->lastErrorCode = null;
            $this->lastErrorMessage = null;
            $conn = $this->conn_id;
            if (isset($params) && count($params) > 0) {
                if ($this->mysql) {
                    foreach ($params as &$param) {
                        if ($param === false) {
                            $param = 0;
                        }
                        if ($param === '') {
                            $param = null;
                        }                    
                    }
                }
                $stmt = $conn->prepare($query);
                if ($stmt->execute($params)) {
                    $result = $stmt->rowCount();
                } else {
                    $result = 0;
                }
            } else {
                $result = $conn->exec($query);
            }
            return $result;
        } catch (Exception $exception) {
            $this->lastErrorMessage = $exception->getMessage();
            $this->lastErrorCode = $exception->getCode();
            throw new Exception("[{$this->lastErrorCode}] {$this->lastErrorMessage}.\n$query");
            // return -1;
        }
    }

    /**
     * @var PDOStatement
     */
    private $statement;

    /**
     * @param string $query
     * @param array $params
     * @return mixed|void
     */
    public function prepare(string $query, $params = [])
    {
        $this->statement = $this->conn_id->prepare($query, [PDO::ATTR_CURSOR => PDO::CURSOR_FWDONLY]);
        $this->statement->execute($params);
        return $this->statement;
    }

    /**
     * @param string $query
     * @param array $params
     * @return RecordSet
     */
    public function iterator(string $query, $params = []): RecordSet {
        $this->statement = $this->conn_id->prepare($query, array(PDO::ATTR_CURSOR => PDO::CURSOR_FWDONLY));
        $this->statement->execute($params);
        return new RecordSet($this->statement);
    }

    public function fetch()
    {
       if (!isset($this->statement)) {
           return false;
       }
       return $this->statement->fetch($this->pdoFetchType);
    }

    public function updateSequence($table, $key = 'id'): void
    {
        if ($this->pgsql) {
            $this->execute("SELECT"." setval('{$table}_{$key}_seq', (SELECT MAX({$key}) FROM {$table})+1);");
        }
    }

    public function getConn(): PDO
    {
        return $this->conn_id;
    }
}
