<?php

namespace Minhnhc\Form;


class DownloadObject
{
    const MINE_TYPE_EXCEL = 1;
    const MINE_TYPE_OCTET = 2;
    const MINE_TYPE_IMAGE = 3;
    const MINE_TYPE_PDF = 4;
    const MINE_TYPE_WORD = 5;

    public $filename = null;
    public $data = null;
    public $mine = self::MINE_TYPE_OCTET;

    public function __construct($filename=null, $data=null) {
        $this->data = $data;
        $this->filename = $filename;
    }
}
