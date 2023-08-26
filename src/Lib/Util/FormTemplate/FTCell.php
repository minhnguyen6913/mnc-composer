<?php

namespace App\Lib\Util\FormTemplate;

class FTCell
{
    public $value = null;
    public int $colspan = 1;
    public bool $background = false;
    public ?string $width = null;
    public ?string $style = null;
    public ?string $verticalAlign = null;

    public function __construct($value, $colspan=1, $background=false, $width=null, $verticalAlign=null)
    {
        $this->value = $value;
        $this->colspan = $colspan;
        $this->width = $width;
        $this->background = $background;
        $this->verticalAlign = $verticalAlign;
    }

    public function item(): ?FTItem {
        if ($this->value instanceof FTItem) {
            return $this->value;
        }
        return null;
    }

    public function table(): ?FTTable {
        if ($this->value instanceof FTTable) {
            return $this->value;
        }
        return null;
    }

    public function set($property, $value): FTCell {
        $this->$property = $value;
        return $this;
    }
}
