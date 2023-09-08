<?php


namespace Minhnhc\Form;


class FileInfo
{
    public $name = null;
    public $type = null;
    public $tmpName = null;
    public $size = null;
    public $error = null;

    public function __construct($name=null, $type=null, $tmpName=null, $size=null, $error = null) {
        $this->name = $name;
        $this->type = $type;
        $this->tmpName = $tmpName;
        $this->size = $size;
        $this->error = $error;
    }
}
