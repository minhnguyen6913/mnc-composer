<?php

namespace Minhnhc\Form;

class SearchInfo
{
    public array $items = [];
    public function __construct(?array $info){
        if (!isset($info)){
            return;
        }
        $this->items = [];
        foreach ($info as $key => $rec) {
            $item = new SearchItem();
            $item->name = $key;
            $item->values = @$rec['values'];
            $item->value1 = @$rec['value1'];
            $item->value2 = @$rec['value2'];
            $item->type = @$rec['type'];
            $item->searchNull = @$rec['searchNull'];
            $this->items[$key] = $item;
        }
    }

    /**
     * @param $name
     * @return ?SearchItem
     */
    public function item($name): ?SearchItem
    {
        if (!isset($this->items[$name])) {
            return null;
        }
        return $this->items[$name];
    }

    public function exists($name) : bool {
        return isset($this->items[$name]);
    }

    public function size(): int {
        return count($this->items);
    }

    public function keys(): array {
        return array_keys($this->items);
    }
}
