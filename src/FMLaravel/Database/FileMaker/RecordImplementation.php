<?php namespace FMLaravel\Database\FileMaker;

use FileMaker_Record_Implementation;

class RecordImplementation extends FileMaker_Record_Implementation
{

    public function getAllFields()
    {
        return array_map(function ($v) {
            return count($v) > 1 ? $v : reset($v);
        }, $this->_fields);
    }
}
