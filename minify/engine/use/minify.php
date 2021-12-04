<?php

class Minify extends Genome {

    public static function __callStatic(string $kin, array $lot = []) {
        if (parent::_($k = '.' . strtolower($kin))) {
            return parent::__callStatic($k, $lot);
        }
        return parent::__callStatic($kin, $lot);
    }

}