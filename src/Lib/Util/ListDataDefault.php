<?php


namespace Sannomiya\Util;


class ListDataDefault implements ListDataInterface
{

    public function map($name, $param = null, $fixParam = null, $filter = null, $max = null): ?array
    {
        return [];
    }

    public function list($name, $param = null, $fixParam = null, $filter = null, $max = null): ?array
    {
        return [];
    }

    public function tree($rs, $parentField = 'parent', $idField = 'id', $nameField = 'name'): ?array
    {
        return [];
    }

    public function setIDs(?array $ids)
    {

    }

    public function reverse($name, $value)
    {
        return null;
    }
}
