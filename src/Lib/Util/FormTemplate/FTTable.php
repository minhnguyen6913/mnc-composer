<?php

namespace App\Lib\Util\FormTemplate;

class FTTable
{
    const CAPTION_TOP = 1;
    const CAPTION_LEFT = 2;
    const CAPTION_NONE = 0;

    public array $rows = [];
    public ?string $width = null;
    public bool $border = false;
    public ?string $class = null;

    public function __construct($width = null, $border = false)
    {
        $this->border = $border;
        $this->width = $width;
    }

    public function setCell(FTCell $cell, $row, $col) {
        $count = count($this->rows);
        if ($count <= $row) {
            for($i=$count;$i<=$row;$i++) {
                $this->rows[] = [];
            }
        }
        $count = count($this->rows[$row]);
        if ($count <= $col) {
            for($i=$count;$i<=$col;$i++) {
                $this->rows[$row][] = null;
            }
        }
        $this->rows[$row][$col] = $cell;
    }
    public function getCel($row, $col): ?FTCell {
        if (isset($this->rows[$row]) && isset($this->rows[$row][$col])) {
            return $this->rows[$row][$col];
        }
        return null;
    }

    public function toArray(): array{
        return [
            'width'=> $this->width,
            'rows' => $this->rows,
            'border' => $this->border,
            'class' => $this->class,
        ];
    }

    public function __toString(): string
    {
        return json_encode($this->toArray());
    }

    private static function createCell($value, $type, $colspan=1, $background=false, $width=null, $verticalAlign = null): FTCell {
        return new FTCell(new FTItem($value, $type), $colspan, $background, $width, $verticalAlign);
    }
    public static function createCaptionCell($value, $colspan=1, $background=false, $width=null, $verticalAlign = null): FTCell {
        return self::createCell($value, FTItem::CAPTION, $colspan, $background, $width, $verticalAlign);
    }
    public static function createHeaderCell($value, $colspan=1, $background=false, $width=null, $verticalAlign = null): FTCell {
        return self::createCell($value, FTItem::HEADER, $colspan, $background, $width, $verticalAlign);
    }
    public static function createFreeTextCell($value, $colspan=1, $background=false, $width=null, $verticalAlign = null): FTCell {
        return self::createCell($value, FTItem::FREETEXT, $colspan, $background, $width, $verticalAlign);
    }
    public static function createFieldCell($value, $colspan=1, $background=false, $width=null, $verticalAlign = null): FTCell {
        return self::createCell($value, FTItem::FIELD, $colspan, $background, $width, $verticalAlign);
    }

    /**
     * @param FTTable|null $table
     * @param string $fields
     * @param int $captionMode
     * @param null $header
     * @param null $headerWidth
     * @return FTTable
     */
    public static function createTable(?FTTable $table, string $fields, int $captionMode = self::CAPTION_TOP, $header=null, $headerWidth=null): FTTable {
        if (!isset($table)) {
            $table = new FTTable();
        }
        if (!is_array($fields)) {
            $fields = explode(",", $fields);
        }
        if ($captionMode == self::CAPTION_NONE) {
            $row = [];
            if (isset($header)) {
                $row[] = self::createHeaderCell($header, 1, false, $headerWidth);
            }
            foreach ($fields as $field) {
                if (str_starts_with($field, "#") && str_ends_with($field, "#")) {
                    $field = substr($field, 1, strlen($field) -2);
                    $row[] = self::createFreeTextCell($field);
                }else{
                    $row[] = self::createFieldCell($field);
                }
            }
            $table->rows[] = $row;
        }elseif ($captionMode == self::CAPTION_TOP){
            $row1 = [];
            $row2 = [];
            if (isset($header)) {
                $row1[] = self::createFreeTextCell('');
                $row2[] = self::createHeaderCell($header, 1, false, $headerWidth);
            }
            foreach ($fields as $field) {
                $cell = self::createCaptionCell($field);
                $cell->verticalAlign = 'bottom';
                $row1[] = $cell;
                $row2[] = self::createFieldCell($field);
            }
            $table->rows[] = $row1;
            $table->rows[] = $row2;
        }elseif ($captionMode == self::CAPTION_LEFT) {
            $row = [];
            foreach ($fields as $field) {
                $row[] = self::createCaptionCell($field);
                $row[] = self::createFieldCell($field);
            }
            $table->rows[] = $row;
        }
        return $table;
    }

    public function set($property, $value): FTTable {
        $this->$property = $value;
        return $this;
    }

}
