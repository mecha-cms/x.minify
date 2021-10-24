<?php

$every = static function(array $tokens, callable $fn, string $in = null) {
    if ("" === ($in = trim($in))) {
        return "";
    }
    $chops = preg_split('/(' . implode('|', $tokens) . ')/i', $in, null, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
    $out = "";
    foreach ($chops as $i => $chop) {
        $prev = $chops[$i - 1] ?? "";
        $next = $chops[$i + 1] ?? "";
        $out .= $fn(trim($prev), trim($chop), trim($next), $prev, $chop, $next);
    }
    return $out;
};

$_css = function(string $in, int $comment = 2, int $quote = 2) use(&$every) {
    if ("" === ($in = trim($in))) {
        return "";
    }
    return $every([
        // TODO
    ], function($prev, $current, $next) {
        if ("" === $current) {
            return "";
        }
        // Comment token
        if (0 === strpos($current, '/*') && '*/' === substr($current, -2)) {
            // ...
            return ""; // Remove comment(s)
        }
        $current = preg_replace('/\s+/', ' ', $current);
        // Function token
        if (
            ')' === substr($current, -1) && strpos($current, '(') > 0 &&
            preg_match('/^([a-z-][\w-]*)\(\s*([^;}]+)\s*\)$/', $current, $m)
        ) {
            if ('calc' === $m[1]) {
                return 'calc(' . preg_replace(['/\s*([\(\)])\s*/', '/\s+/'], ['$1', ' '], $m[2]) . ')';
            }
            if ('format' === $m[1]) {
                return "" !== $m[2] ? "format('" . $m[2] . "')";
            }
            if ('url' === $m[1]) {
                if (false !== strpos($m[1], ' ')) {
                    return "url('" . $m[2] . "')";
                }
                return 'url(' . $m[2] . ')';
            }
            return $m[1] . '(' . $every([
                // TODO
            ], function($prev, $current, $next) {
                // TODO
            }, $m[2]) . ')';
        }
        // Keyword token
        if ('@' === $current[0]) {
            // ...
        }
        // String token
        if (
            '"' === $current[0] && '"' === substr($current, -1) ||
            "'" === $current[0] && "'" === substr($current, -1)
        ) {
            return $current;
        }
    }, $in);
};