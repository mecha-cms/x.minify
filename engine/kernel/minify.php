<?php

class Minify extends Genome {

    public static function __callStatic(string $kin, array $lot = []) {
        if (parent::_($kin)) {
            return parent::__callStatic($kin, $lot);
        }
        if (function_exists($f = "x\\minify\\" . strtr(c2f($kin), '-', '_'))) {
            return fire($f, $lot);
        }
        return $lot[0] ?? null;
    }

}