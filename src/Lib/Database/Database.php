<?php
namespace Minhnhc\Database;
use App\Lib\Database\RecordSet;

abstract class Database {
    public const FETCH_TYPE_NUM = 2;
    public const FETCH_TYPE_ASSOC = 1;
    public const DATABASE_TYPE_PDO = 1;

    protected $fetch_type = self::FETCH_TYPE_ASSOC;
    public string $uniqueId = "";
    public const ERROR_RESTRICT = 23000;
    protected ?string $lastErrorMessage = null;
    protected ?string $lastErrorCode = null;
	protected string $schema;
	
	
    static public function setDefaultDatabaseName(?string $databaseName) {
        $_SESSION['DATABASE_DEFAULT_NAME'] = $databaseName;
    }
    static public function getDefaultDatabaseName() {
        if (isset($_SESSION) && isset($_SESSION['DATABASE_DEFAULT_NAME'])) {
            return $_SESSION['DATABASE_DEFAULT_NAME'];
        }
        return null;
    }

    static public function getInstance($database_name=null, $newInstance = false) : Database {


        global $database_config, $database_default;

        if (!isset($database_name)){
            $database_name = self::getDefaultDatabaseName();
        }

        if (!isset($database_name)){
            $database_name = (!isset($database_default) || $database_default == '') ? $_SERVER['HTTP_HOST'] : $database_default;
        }

        if (is_array($database_name)) {
            $data_info = $database_name;
            $request_database_name = "_database_" . @$data_info['name'];
        }else{
            if (!isset($database_config[$database_name])) {
                exit("Database $database_name not found!");
            }
            $data_info = $database_config[$database_name];
            $request_database_name = "_database_$database_name";
        }

        // Get instance from REQUEST
        if (!$newInstance && isset($_REQUEST[$request_database_name])){
            return $_REQUEST[$request_database_name];
        }
        switch ($data_info["database_type"]) {
            case self::DATABASE_TYPE_PDO:
                $ret = new PDODatabase($data_info);
                break;
            default:
                exit("Database type {$data_info["database_type"]} not implemented.");
        }

        // Save instance in REQUEST
        if (!$newInstance) {
            $_REQUEST[$request_database_name] = $ret;
        }

        return $ret;
    }

    /**
     * @return int
     */
    public function getFetchType(): int
    {
        return $this->fetch_type;
    }

    /**
     * @param int $fetch_type
     */
    public function setFetchType(int $fetch_type)
    {
        $this->fetch_type = $fetch_type;
    }

    public function getMap($query, $params=[]): array
    {
        $fetch_type = $this->getFetchType();
        $this->setFetchType(self::FETCH_TYPE_NUM);
        $rs = $this->getRecordSet( $query, $t, 0, -1, $params );
        $this->setFetchType($fetch_type);
        $ret = [];
        if (is_array ( $rs )) {
            foreach ( $rs as $rec ) {
                $ret [$rec [0]] = $rec [1];
            }
        }
        return $ret;
    }

    public function getMapOfMap($query, $keys, $params=[]): array
    {
        $fetch_type = $this->getFetchType();
        $this->setFetchType(self::FETCH_TYPE_ASSOC);
        $rs = $this->getRecordSet( $query, $t, 0, -1, $params );
        $this->setFetchType($fetch_type);
        if (!is_array($keys)){
            $keys = explode(',', $keys);
        }
        $ret = [];
        if (is_array ( $rs )) {
            foreach ( $rs as $rec ) {
                $map_keys = [];
                foreach ($keys as $key) {
                    $map_keys[] = @$rec[$key];
                }
                $ret [implode('_', $map_keys)] = $rec;
            }
        }
        return $ret;
    }

    public function getList($query, $params=[]): array
    {
        $fetch_type = $this->getFetchType();
        $this->setFetchType(self::FETCH_TYPE_NUM);
        $rs = $this->getRecordSet( $query, $t, 0, -1, $params );
        $this->setFetchType($fetch_type);
        $ret = [];
        foreach ($rs as $rec) {
            $ret[] = $rec[0];
        }
        return $ret;
    }

    public function getListOfList($query, $params=[], $max=null): array
    {
        if (!is_numeric($max)) {
            $max = -1;
        }
        $fetch_type = $this->getFetchType();
        $this->setFetchType(self::FETCH_TYPE_NUM);
        $rs = $this->getRecordSet( $query, $t, 0, $max, $params );
        $this->setFetchType($fetch_type);
        return  $rs;
    }

//    public function updateSerial($table, $serial_field="id"){
//        $serial = $this->get("select max($serial_field) from $table");
//        $this->execute("SELECT setval('{$table}_{$serial_field}_seq'::regclass, $serial)");
//    }

    public function getRecordValues($rec, $fields): array
    {
        $ret = [];
        foreach ($fields as $field) {
            $ret[] = $rec[$field];
        }
        return $ret;
    }

    public function checkUnique(array $rs, string $table, $checkFields, ?string &$errorValues, $where = null, $keyFields='id', $update = true): bool {
        if (!is_array($checkFields)) {
            $checkFields = explode(",", $checkFields);
        }
        if (!is_array($keyFields)) {
            $keyFields = explode(",", $keyFields);
        }
        $selectField = $checkFields[0];
        $whereFields = [];
        foreach ($checkFields as $field){
            $whereFields[] = "$field=?";
        }
        $whereFields = implode(" AND ", $whereFields);

        $values = [];
        if (isset($where)){
            $where = "and $where";
        }

        foreach ($rs as $rec) {
            $ok = true;
            $params = $this->getRecordValues($rec,$checkFields);
            $value = implode(',', $params);
            if (in_array($value, $values)) {
                $ok = false;
            }else if (isset($value) && $value!=''){
                $values[] = $value;
                // Check key
                $checkKeys = [];
                if ($update) {
                    foreach ($keyFields as $key) {
                        if (isset($rec[$key])){
                            if (is_numeric($rec[$key])) {
                                $checkKeys[] = "$key <> {$rec[$key]}";
                            }else{
                                $checkKeys[] = "$key <> '{$rec[$key]}'";
                            }

                        }
                    }
                }
                if (count($checkKeys) == count($keyFields)) {
                    $whereKey = ' AND ' . '(' . implode(' OR ', $checkKeys) . ')';
                }else{
                    $whereKey = '';
                }
                $check = $this->get("select "."$selectField from $table where $whereFields $whereKey $where", $params);

                if (isset($check)){
                    $ok = false;
                }
            }

            if (!$ok){
                $errorValues = $value;
                return false;
            }
        }
        return true;
    }

    public abstract function begin();
    public abstract function commit();
    public abstract function rollback();
    public abstract function close();

    /**
     * Get first field in first record return by $query
     * @param string $query
     * @param array $params
     * @return mixed
     */
    public abstract function get(string $query, $params=[]);
    public abstract function getRecord($query, $params=[]);
    public abstract function getRecordSet($query, &$total, $start=0, $page_size=-1, $params=[]): array;
    public abstract function execute($query, $params=[]);
    public abstract function insertedId($name = null): ?int;
    public abstract function getTotal($query, $params = []): int;

    /**
     * Prepare SQL statement for fetch
     * @param string $query
     * @param array $params
     * @return mixed
     */
    public abstract function prepare(string $query, $params=[]);

    /**
     * Use this to get record from statement that prepared
     * @return mixed
     */
    public abstract function fetch();

    public abstract function iterator(string $query, $params=[]): RecordSet;

    public abstract function updateSequence($table): void;

    public abstract function getConn();

    /**
     * @return string|null
     */
    public function getLastErrorCode(): ?string
    {
        return $this->lastErrorCode;
    }

    /**
     * @return string
     */
    public function getSchema(): string
    {
        return $this->schema;
    }


    /**
     * @param int $time second
     * @return array
     */
    public function getLocking(int $time = 0, ?string $pattern = null): array
    {
        if (isset($pattern)) {
            $pattern = str_replace("'", "''", $pattern);
            $query = "SELECT id, user, time, state, info 
            FROM information_schema.processlist 
            WHERE command != 'Sleep' and state='System lock' and time > $time and info like '%$pattern' ORDER BY time DESC, id;";
        }else{
            $query = "SELECT id, user, time, state, info 
            FROM information_schema.processlist 
            WHERE command != 'Sleep' and state='System lock' and time > $time ORDER BY time DESC, id;";
        }
        return $this->getRecordSet($query, $t);
    }

    public function isLocking(int $time = 0, ?string $pattern = null): bool
    {
        $rs = $this->getLocking($time, $pattern);
        return count($rs) > 0;
    }

    public function killLocking(int $time = 0, ?string $pattern = null): int {
        $ret = 0;
        $rs = $this->getLocking($time, $pattern);
        foreach ($rs as $rec) {
            $this->execute("kill {$rec['id']}");
            $ret++;
        }
        return $ret;
    }
}



