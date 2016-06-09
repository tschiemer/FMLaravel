<?php namespace FMLaravel\Database\FileMaker;

interface RecordInterface
{

    /** Returns associative array of all fields such that only repetitions fields (w.r.t. the layout) are returned as
     * numerically indexed array values.
     * @return array
     */
    public function getAllFields();
}
