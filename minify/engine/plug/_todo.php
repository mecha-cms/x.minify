<?php

$every = static function(array $tokens, callable $fn, string $in = null, string $flag = 'i') {
    if ("" === ($in = trim($in))) {
        return "";
    }
    $pattern = strtr('(?:' . implode(')|(?:', $tokens) . ')', ['/' => "\\/"]);
    $chops = preg_split('/(' . $pattern . ')/' . $flag, $in, null, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
    $out = "";
    while ($chops) {
        $chop = array_shift($chops);
        if ("" === ($value = trim($chop))) {
            continue;
        }
        $out .= $fn($value, $chop);
    }
    return $out;
};

$CSS = function(string $in, int $comment = 2, int $quote = 2) use(&$every) {
    if ("" === ($in = trim($in))) {
        return "";
    }
    $out = $every(['/\*[\s\S]*?\*/'], function($value) use($comment) {
        if (1 === $comment) {
            return $value;
        }
        if ('/*' === substr($value, 0, 2) && '*/' === substr($value, -2)) {
            if (2 === $comment) {
                if (
                    // Detect special comment(s) from the third character.
                    // Should be an `!` or `*` → `/*! asdf */` or `/** asdf */`
                    isset($value[2]) && false !== strpos('!*', $value[2]) ||
                    // Detect license comment(s) from the content phrase.
                    // It should contains character(s) like `@license`
                    false !== strpos($value, '@licence') || // noun
                    false !== strpos($value, '@license') || // verb
                    false !== strpos($value, '@preserve')
                ) {
                    return $value;
                }
            }
            return ""; // Remove!
        }
        return $value;
    }, $in);
    return $every([
        // Match any comment
        '/\*[\s\S]*?\*/',
        // Match empty selector
        '[^{}]+\{\s*\}',
        // Match selector until an `{`
        '[^{}]+\{',
        // Match key-value pair(s) with optional hack(s)
        // <https://github.com/4ae9b8/browserhacks>
        '[!#$%&()*+,./:<=>?@\[\]_`|~]?[a-z-][a-z\d-]*\s*:\s*[^};]*(?:[;]?\s*[}]|[;])'
    ], function($value) use(&$every) {
        if ('/*' === substr($value, 0, 1) && '*/' === substr($value, -2)) {
            return $value; // Keep!
        }
        $value = preg_replace('/\s+/', ' ', $value);
        if ('{}' === substr($value, -2) || '{ }' === substr($value, -3)) {
            return ""; // Remove empty selector(s)
        }
        if ('{' === substr($value, -1)) {
            return $every([
                '[~+>{,]'
            ], function($value) {
                return $value;
            }, $value);
        }
        if (false !== strpos('};', substr($value, -1))) {
            $value = $every([
                // Key
                '[a-z-][a-z\d-]*\s*:',
                // Value
                '[^};]+'
            ], function($value) use(&$every) {
                // Key
                if (':' === substr($value, -1)) {
                    return strtr($value, [
                        ' ' => ""
                    ]);
                }
                $color = '#(?:[a-f\d]{1,2}){3,4}\b';
                $number = '-?(?:(?:\d+)?\.)?\d+';
                // <https://www.w3.org/TR/css-values-4>
                $unit = $number . '(?:em|rem|ex|rex|cap|rcap|ch|rch|ic|ric|lh|rlh|vw|vh|vi|vb|vmin|vmax|cm|mm|Q|in|pt|pc|px|deg|grad|rad|turn|s|ms|Hz|kHz|dpi|dpcm|dppx|%)';
                // Value
                return $every([
                    // Match function which may contains string
                    '\b[a-z-][a-z\d-]*\((?:"(?:[^"\\\]|\\\.)*"|\'(?:[^\'\\\]|\\\.)*\'|[^{};]+)\)',
                    // Match HEX color code
                    $color,
                    // Match number(s) with unit
                    $unit,
                    // Match number(s)
                    $number
                ], function($value) use(&$every, $color, $number, $unit) {
                    if (')' === substr($value, -1) && strpos($value, '(') > 0 && preg_match('/^([a-z-][a-z\d-]*)\(\s*([\s\S]*)\s*\)$/', $value, $m)) {
                        $name = $m[1];
                        $params = $m[2];
                        if (
                            $params && (
                                '"' === $params[0] && '"' === substr($params, -1) ||
                                "'" === $params[0] && "'" === substr($params, -1)
                            )
                        ) {
                            $raw = substr($params, 1, -1);
                        }
                        if ('calc' === $name) {
                            return 'calc(' . preg_replace('/\s*[()]\s*/', '$1', $params) . ')';
                        }
                        if ('format' === $name) {
                            return "format('" . $raw . "')";
                        }
                        if ('url' === $name) {
                            return false !== strpos($raw, ' ') ? "url('" . $raw . "')" : 'url(' . $raw . ')';
                        }
                        return $name . '(' . $every([
                            $color,
                            $unit,
                            $number
                        ], function($value) use($unit) {
                            if ('#' === $value[0]) {
                                return $value; // TODO: Minify HEX color
                            }
                            if (is_numeric($value)) {
                                // Convert `0.5` to `.5`
                                return ('0.' === substr($value, 0, 2) ? substr($value, 1) : $value) . ' ';
                            }
                            if (preg_match('/^' . $unit . '$/', $value)) {
                                $v = (string) floatval($value);
                                // Convert `0px` to `0`
                                if ('0' === $v) {
                                    $unit = substr($value, 1);
                                    return ('deg' !== $unit && '%' !== $unit ? '0' : $value) . ' ';
                                }
                                // Convert `0.5px` to `.5px`
                                return ('0.' === substr($value, 0, 2) ? substr($value, 1) : $value) . ' ';
                            }
                            return $value;
                        }, $params) . ')';
                    }
                    if ('#' === $value[0]) {
                        return $value; // TODO: Minify HEX
                    }
                    if (is_numeric($value)) {
                        // Convert `0.5` to `.5`
                        return ('0.' === substr($value, 0, 2) ? substr($value, 1) : $value) . ' ';
                    }
                    if (preg_match('/^' . $unit . '$/', $value)) {
                        $v = (string) floatval($value);
                        // Convert `0px` to `0`
                        if ('0' === $v) {
                            $unit = substr($value, 1);
                            return ('deg' !== $unit && '%' !== $unit ? '0' : $value) . ' ';
                        }
                        // Convert `0.5px` to `.5px`
                        return ('0.' === substr($value, 0, 2) ? substr($value, 1) : $value) . ' ';
                    }
                    return $value;
                }, $value);
            }, $value);
            $value = $every([
                // Match string
                '(?:"(?:[^"\\\]|\\\.)*"|\'(?:[^\'\\\]|\\\.)*\')',
                // This is to remove white-space(s) between number/unit and punctuation, outside of the string
                '[();,/]'
            ], function($value) {
                // Misc
                $values = [
                    'background:none' => 'background:0 0',
                    'border:none' => 'border:0',
                    'font-weight:bold' => 'font-weight:700',
                    'font-weight:normal' => 'font-weight:400',
                    'margin:0 0 0 0' => 'margin:0',
                    'margin:0 0 0' => 'margin:0',
                    'margin:0 0' => 'margin:0',
                    'outline:none' => 'outline:0'
                ];
                return $values[$value] ?? $value;
            }, $value);
            return ';}' === substr($value, -2) ? substr($value, 0, -2) . '}' :  $value;
        }
        return $value;
    }, $out);
};

test($CSS(content(__DIR__ . '/../../test/css') ?? "")); exit;