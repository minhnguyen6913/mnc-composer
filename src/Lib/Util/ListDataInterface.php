<?php
namespace Minhnhc\Util;

interface ListDataInterface
{
    public function setIDs(?array $ids);
    public function map($name, $param = null, $fixParam = null, $filter = null, $max = null): ?array;
    public function list($name, $param = null, $fixParam = null, $filter = null, $max = null): ?array;
    public function reverse($name, $value);
    public function tree($rs, $parentField = 'parent', $idField='id', $nameField='name'): ?array;
}
