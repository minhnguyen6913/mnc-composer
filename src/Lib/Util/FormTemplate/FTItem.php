<?php

namespace App\Lib\Util\FormTemplate;

class FTItem
{
    const HEADER = 4;
    const CAPTION = 1;
    const FIELD = 2;
    const FREETEXT = 3;

    public int $type = self::CAPTION;
    public string $value;

    public function __construct($value, $type = self::FIELD)
    {
        $this->value = $value;
        $this->type = $type;
    }

    public function set($property, $value): FTItem {
        $this->$property = $value;
        return $this;
    }
}
