<?php namespace FMLaravel\Database;

class Helpers
{
    public static function escape($str)
    {
        $map = [
            '@'     => '\@',
            '#'     => '\#',
            '?'     => '\?',
            '""'    => '\"\"',
            '*'     => '\*',
            '//'    => '\/\/'
        ];

        return str_replace(array_keys($map), array_values($map), $str);
    }
}
