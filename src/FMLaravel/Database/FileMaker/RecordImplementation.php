<?php namespace FMLaravel\Database\FileMaker;

use FileMaker_Record_Implementation;

class RecordImplementation extends FileMaker_Record_Implementation
{

    public function getAllFields()
    {
        // fields are originally saved as numerically indexed arrays (because of repetition fields), even if it is
        // only a normal field. So for any array consisting of only one element, assume it's not a repetition field
        // and just set the first elements value as actual value, otherwise (when it's a repetition field) keep
        // the array
        return array_map(function ($v) {
            return count($v) > 1 ? $v : reset($v);
        }, $this->_fields);
    }
}
