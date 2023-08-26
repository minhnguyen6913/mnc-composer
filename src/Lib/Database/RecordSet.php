<?php


namespace App\Lib\Database;


use Iterator;

class RecordSet implements Iterator
{
    /** @var \PDOStatement $pdoStatement The PDO Statement to execute */
    protected $pdoStatement;

    /** @var int $key The cursor pointer */
    protected $key;

    /** @var  bool|array The resultset for a single row */
    protected $result;

    /** @var  bool $valid Flag indicating there's a valid resource or not */
    protected $valid = false;

    public function __construct(\PDOStatement $PDOStatement)
    {
        $this->pdoStatement = $PDOStatement;
        $this->valid = true;
    }

    /**
     * @inheritDoc
     */
    public function current()
    {
        return $this->result;
    }

    /**
     * @inheritDoc
     */
    public function next()
    {
        $this->key++;
        $this->result = $this->pdoStatement->fetch(
            \PDO::FETCH_ASSOC,
            \PDO::FETCH_ORI_ABS,
            $this->key
        );
        if (false === $this->result) {
            $this->valid = false;
            return null;
        }
    }

    /**
     * @inheritDoc
     */
    public function key()
    {
        return $this->key;
    }

    /**
     * @inheritDoc
     */
    public function valid(): bool
    {
        return $this->valid;
    }

    /**
     * @inheritDoc
     */
    public function rewind()
    {
        $this->valid = true;
        $this->key = 0;
    }
}
