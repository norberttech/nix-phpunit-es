<?php

namespace NixPHPUnitEs;

class Memory
{
    public static function toString(int $size) : string
    {
        $unit=['b','kb','mb','gb','tb','pb'];
        return @round($size/ (1024 ** ($i = floor(log($size, 1024)))),2).' '.$unit[$i];
    }
}