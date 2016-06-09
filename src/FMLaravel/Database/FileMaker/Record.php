<?php namespace FMLaravel\Database\FileMaker;

use FileMaker_Record;
use FMLaravel\Database\FileMaker\RecordInterface;
use FMLaravel\Database\FileMaker\RecordImplementation;

class Record extends FileMaker_Record implements RecordInterface
{

    public function __construct(&$layout)
    {
        $this->_impl = new RecordImplementation($layout);
    }

    public function getAllFields()
    {
        return $this->_impl->getAllFields();
    }
}
