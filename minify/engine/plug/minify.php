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

$token_boolean = '\b(?:true|false)\b';
$token_number = '-?(?:(?:\d+)?\.)?\d+';
$token_string = '(?:"(?:[^"\\\]|\\\.)*"|\'(?:[^\'\\\]|\\\.)*\')';

$token_css_combinator = '[~+>]';
$token_css_hack = '[!#$%&()*+,./:<=>?@\[\]_`|~]';
$token_css_hex = '#(?:[a-f\d]{1,2}){3,4}';
$token_css_property = '[a-z-][a-z\d-]*';
$token_css_value = '(?:' . $token_string . '|[^;])*';

// <https://www.w3.org/TR/css-values-4>
$token_css_unit = '%|Hz|Q|cap|ch|cm|deg|dpcm|dpi|dppx|em|ex|grad|ic|in|kHz|lh|mm|ms|pc|pt|px|rad|rcap|rch|rem|rex|ric|rlh|s|turn|vb|vh|vi|vmax|vmin|vw';
$token_css_number = $token_number . '(?:' . $token_css_unit . ')';

$minify_number = static function($value) use(&$minify_number) {
    if ('-' === $value[0] && strlen($value) > 1) {
        $number = $minify_number(substr($value, 1));
        return '0' === $number ? '0' : '-' . $number;
    }
    if (is_numeric($value)) {
        if (false !== strpos($value, '.')) {
            // Convert `5.0` to `5.`
            // Convert `5.10` to `5.1`
            $value = rtrim($value, '0');
            // Convert `5.` to `5`
            $value = rtrim($value, '.');
        }
        // Convert `0.5` to `.5`
        return ('0.' === substr($value, 0, 2) ? substr($value, 1) : $value);
    }
    return $value;
};

$minify_css_color = static function($value) use(
    &$every,
    &$minify_css_color,
    &$minify_number,
    &$token_number
) {
    if ('#' === $value[0]) {
        $value = strtolower(preg_replace('/^#([a-f\d])\1([a-f\d])\2([a-f\d])\3(?:([a-f\d])\4)?$/i', '#$1$2$3$4', $value));
        if (9 === strlen($value) && 'ff' === substr($value, -2)) {
            return substr($value, 0, -2); // Solid HEX color
        }
        if (5 === strlen($value) && 'f' === substr($value, -1)) {
            return substr($value, 0, -1); // Solid HEX color
        }
        return $value;
    }
    $value = preg_replace('/\s*([(),\/])\s*/', '$1', $value);
    if (0 === strpos($value, 'rgba(')) {
        if (',1)' === substr($value, -3) || '/1)' === substr($value, -3)) {
            // Solid alpha channel, convert it to RGB
            $hex = $minify_css_color($rgb = 'rgb(' . substr($value, 5, -3) . ')');
            return strlen($hex) < strlen($rgb) ? $hex : $rgb;
        }
        if (preg_match('/^rgba\((\d+)[, ](\d+)[, ](\d+)[,\/](' . $token_number . ')\)$/', $value, $m)) {
            $hex = $minify_css_color('#' . dechex($m[1]) . dechex($m[2]) . dechex($m[3]) . dechex(((float) $m[4]) * 255));
            return strlen($hex) < strlen($value) ? $hex : $value;
        }
    }
    if (0 === strpos($value, 'rgb(')) {
        if (preg_match('/^rgb\((\d+)[, ](\d+)[, ](\d+)\)$/', $value, $m)) {
            $hex = $minify_css_color('#' . dechex($m[1]) . dechex($m[2]) . dechex($m[3]));
            return strlen($hex) < strlen($value) ? $hex : $value;
        }
    }
    return $value;
};

$minify_css_value = static function($value) use(
    &$minify_number,
    &$token_css_property,
    &$token_number
) {
    $number = $value;
    $unit = "";
    if (!is_numeric($value) && preg_match('/^(' . $token_number . ')(' . $token_css_property . '|%)$/i', $value, $m)) {
        $number = $m[1];
        $unit = $m[2];
    }
    $number = $minify_number($number);
    if ('0' === $number) {
        // `0%` or `0deg`
        if ('%' === $unit || 'deg' === $unit) {
            return $number . $unit;
        }
        return $number;
    }
    return $number . $unit;
};

$minify_css = function(string $in, int $comment = 2, int $quote = 2) use(
    &$every,
    &$minify_css,
    &$minify_css_color,
    &$minify_css_value,
    &$minify_number,
    &$token_css_combinator,
    &$token_css_hack,
    &$token_css_hex,
    &$token_css_number,
    &$token_css_property,
    &$token_css_value,
    &$token_number,
    &$token_string
) {
    if ("" === ($in = trim($in))) {
        return "";
    }
    $out = $every(['/\*[\s\S]*?\*/'], static function($value) use($comment) {
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
    $out = $every([
        // Match any comment
        '/\*[\s\S]*?\*/',
        // Match `@asdf` until `;`
        '@' . $token_css_property . '\s+(?:' . $token_string . '|[^{};])+\s*;',
        // Match selector and rule(s)
        '@?(?:' . $token_string . '|[^@{};])+\{\s*(?:' . $token_string . '|[^{}]|(?R))*\s*\}'
    ], static function($value) use(
        &$every,
        &$minify_css,
        &$minify_css_color,
        &$minify_css_value,
        &$minify_number,
        &$token_css_combinator,
        &$token_css_hack,
        &$token_css_hex,
        &$token_css_number,
        &$token_css_property,
        &$token_css_value,
        &$token_number,
        &$token_string
    ) {
        if ('/*' === substr($value, 0, 1) && '*/' === substr($value, -2)) {
            return $value; // Keep!
        }
        // Normalize white-space(s) to a space
        $value = preg_replace('/\s+/', ' ', $value);
        // Remove empty rule(s)
        if ('{}' === substr($value, -2) || '{ }' === substr($value, -3)) {
            return "";
        }
        if ('@' === $value[0]) {
            if (';' === substr($value, -1)) {
                $m = explode(' ', $value, 2);
                return $m[0] . ' ' . substr($minify_css('x{x:' . $m[1] . '}'), 4, -1) . ';';
            }
            $rules = $selector = "";
            $m = preg_split('/(' . $token_string . '|[{])/', $value, null, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
            while ($m) {
                if ('{' === ($v = array_shift($m))) {
                    break;
                }
                $selector .= $v;
            }
            $selector = trim($selector);
            $rules = implode("", $m);
            $rules = trim(substr($rules, 0, -1), ' ;');
            if ('@font-face' === $selector) {
                return $selector . substr($minify_css('x{' . $rules . '}'), 1);
            }
            $selector = preg_replace_callback('/\(\s*((?:' . $token_string . '|[^()]|(?R))*)\s*\)/', static function($m) use(&$minify_css) {
                $code = 'x{' . $m[1] . '}';
                return '(' . substr($minify_css($code), 2, -1) . ')';
            }, $selector);
            return $selector . '{' . $minify_css($rules) . '}';
        }
        if ('}' === substr($value, -1)) {
            $rules = $selector = "";
            $m = preg_split('/(' . $token_string . '|[{])/', $value, null, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
            while ($m) {
                if ('{' === ($v = array_shift($m))) {
                    break;
                }
                $selector .= $v;
            }
            $rules = implode("", $m);
            $rules = trim(substr($rules, 0, -1), ' ;');
            $selector = $every([
                // Match nesting and global selector(s)
                '\s*[&*]\s*',
                // Match function-like pseudo class/element selector(s)
                '\s*:{1,2}' . $token_css_property . '\((?:' . $token_string . '|[^()]|(?R))*\)\s*',
                // Match pseudo class/element selector(s)
                '\s*:{1,2}' . $token_css_property . '\s*',
                // Match attribute selector(s)
                '\s*\[\s*(?:' . $token_string . '|[^][]|(?R))*\s*\]\s*'
            ], static function($value, $chop) use(
                &$every,
                &$minify_css,
                &$token_css_property,
                &$token_css_value,
                &$token_string
            ) {
                if (':' === $value[0]) {
                    if (')' === substr($value, -1)) {
                        return preg_replace_callback('/\(\s*((?:' . $token_string . '|[^()]|(?R))*)\s*\)/', static function($m) use(&$minify_css) {
                            // Assume that argument(s) of `:foo()` is a complete CSS declaration so we can minify it recursively.
                            // FYI, that `{x:x}` part is a dummy just to make sure that the minifier will not remove the whole rule(s)
                            // since `x{x:x}` is not considered as a selector with empty rule(s).
                            $code = $m[1] . '{x:x}';
                            return '(' . substr($minify_css($code), 0, -5) . ')';
                        }, $chop);
                    }
                    return $chop;
                }
                if ('[' === $value[0] && ']' === substr($value, -1)) {
                    return preg_replace_callback('/=(' . $token_string . ')(?:\s*([is]))?\]/i', static function($m) use(&$token_css_property) {
                        $value = substr($m[1], 1, -1);
                        if ("" === $value) {
                            return '=""]';
                        }
                        // <https://mothereff.in/unquoted-attributes>
                        if (ctype_alpha($value[0]) && preg_match('/^' . $token_css_property . '$/i', $value)) {
                            return '=' . $value . (isset($m[2]) ? ' ' . $m[2] : "") . ']';
                        }
                        return "='" . strtr($value, ["'" => "\\'"]) . "'" . ($m[2] ?? "") . ']';
                    }, $chop);
                }
                return $chop;
            }, $selector);
            $selector = $every([
                $token_string,
                $token_css_combinator,
                '[,]'
            ], static function($value) {
                return $value;
            }, $selector);
            $rules = $every([
                '/\*[\s\S]*?\*/',
                '[;]'
            ], static function($value) use(
                &$minify_css_value,
                &$token_css_hack,
                &$token_css_number,
                &$token_css_value,
                &$token_number
            ) {
                if ('/*' === substr($value, 0, 2) && '*/' === substr($value, -2)) {
                    return $value;
                }
                // Minify shorthand value(s)
                if (preg_match('/^(' . strtr($token_css_hack, ['/' => "\\/"]) . '?)(background|border(?:-(?:width|radius))?|margin|padding|outline)\s*:\s*(' . $token_css_value . ')\s*$/i', $value, $m)) {
                    $hack = $m[1];
                    $property = $m[2];
                    $v = $m[3];
                    if ('background' === $property && ('none' === $v || 'transparent' === $v)) {
                        return $hack . $property . ':0 0';
                    }
                    if ('none' === $v) {
                        return $hack . $property . ':0';
                    }
                    $unit = '(' . $token_css_number . '|' . $token_number . ')';
                    // `1px 0 1px 0`
                    if (preg_match('/^' . $unit . ' ' . $unit . ' ' . $unit . ' ' . $unit . '$/i', $v, $m)) {
                        $m[1] = $minify_css_value($m[1]);
                        $m[2] = $minify_css_value($m[2]);
                        $m[3] = $minify_css_value($m[3]);
                        $m[4] = $minify_css_value($m[4]);
                        if ($m[1] === $m[3] && $m[2] === $m[4] && $m[1] !== $m[2] && $m[3] !== $m[4]) {
                            // `1px 0`
                            return $hack . $property . ':' . $m[1] . ' ' . $m[2];
                        }
                        if ($m[1] === $m[2] && $m[2] === $m[3] && $m[3] === $m[4]) {
                            // `1px`
                            return $hack . $property . ':' . $m[1];
                        }
                    }
                    // `1px 0 1px`
                    if (preg_match('/^' . $unit . ' ' . $unit . ' ' . $unit . '$/i', $v, $m)) {
                        $m[1] = $minify_css_value($m[1]);
                        $m[2] = $minify_css_value($m[2]);
                        $m[3] = $minify_css_value($m[3]);
                        if ($m[1] === $m[3] && $m[1] !== $m[2]) {
                            // `1px 0`
                            return $hack . $property . ':' . $m[1] . ' ' . $m[2];
                        }
                        if ($m[1] === $m[2] && $m[2] === $m[3]) {
                            // `1px`
                            return $hack . $property . ':' . $m[1];
                        }
                    }
                    // `1px 0`
                    if (preg_match('/^' . $unit . ' ' . $unit . '$/i', $v, $m)) {
                        $m[1] = $minify_css_value($m[1]);
                        $m[2] = $minify_css_value($m[2]);
                        if ($m[1] === $m[2]) {
                            // `1px`
                            return $hack . $property . ':' . $m[1];
                        }
                    }
                    // `1px`
                    if (preg_match('/^' . $unit . '$/i', $v, $m)) {
                        $m[1] = $minify_css_value($m[1]);
                        return $hack . $property . ':' . $m[1];
                    }
                    return $hack . $property . ':' . $v;
                }
                return $value;
            }, $rules);
            $rules = $every([
                '/\*[\s\S]*?\*/',
                // Match property
                // FYI, that `(?<=^|;)` part was added to make sure that property comes
                // at the beginning of the chunk, or just after the `;` character.
                // I need to make sure that `http:` will not be captured as a roperty.
                '(?<=^|;)' . $token_css_hack . '?' . $token_css_property . '\s*:\s*',
                // Other must be the value
                // $token_css_value
            ], static function($value) use(
                &$every,
                &$minify_css_color,
                &$minify_css_value,
                &$minify_number,
                &$token_css_hex,
                &$token_css_number,
                &$token_css_property,
                &$token_css_value,
                &$token_number,
                &$token_string
            ) {
                if ('/*' === substr($value, 0, 2) && '*/' === substr($value, -2)) {
                    return $value;
                }
                // `margin:`
                if (':' === substr($value, -1)) {
                    return strtr($value, [' ' => ""]);
                }
                // Other(s)
                return $every([
                    // Match function-like which contains only string
                    $token_css_property . '\(\s*' . $token_string . '\s*\)',
                    // Match function-like which may contains string
                    $token_css_property . '\(\s*(?:' . $token_string . '|[^;])*\s*\)',
                    $token_string,
                    $token_css_hex,
                    $token_css_number,
                    $token_number
                ], static function($value) use(
                    &$every,
                    &$minify_css_color,
                    &$minify_css_value,
                    &$minify_number,
                    &$token_css_number,
                    &$token_css_property,
                    &$token_number
                ) {
                    // `format('woff')`
                    if (')' === substr($value, -1) && strpos($value, '(') > 0 && preg_match('/^(' . $token_css_property . ')\(\s*([\s\S]*)\s*\)$/', $value, $m)) {
                        test($value);
                        $name = $m[1];
                        $params = $m[2];
                        // Prepare to remove quote(s) from string-only argument in function
                        if (
                            $params && (
                                '"' === $params[0] && '"' === substr($params, -1) ||
                                "'" === $params[0] && "'" === substr($params, -1)
                            )
                        ) {
                            $raw = substr($params, 1, -1);
                        }
                        if ('calc' === $name) {
                            // Only minify the number, do not remove the unit for now. I have no idea how
                            // this `calc()` thing works in handling the unit(s). As far as I know, the only
                            // valid unit-less number is when they are used as the divisor/multiplicator.
                            // We can remove the space around `*`, `(`, `)` and `/` character safely.
                            $params = preg_replace_callback('/(' . $token_number . '|\s*[*(),\/]\s*)/', static function($m) use(&$minify_number) {
                                return is_numeric($m[1]) ? $minify_number($m[1]) : trim($m[1]);
                            }, $params);
                            return 'calc(' . $params . ')';
                        }
                        if ('format' === $name && "" !== $raw) {
                            // Cannot remove quote(s) in `format()` safely :(
                            return "format('" . $raw . "')";
                        }
                        if ('url' === $name && "" !== $raw) {
                            // Only remove quote(s) around URL if it does not contain space character(s)
                            return false !== strpos($raw, ' ') ? "url('" . $raw . "')" : 'url(' . $raw . ')';
                        }
                        // <https://www.w3.org/TR/css-color-4>
                        if (false !== strpos(',color,device-cmyk,hsl,hsla,hwb,lab,lch,rgb,rgba,', ',' . $name . ',')) {
                            return $minify_css_color($value);
                        }
                        return $name . '(' . $params . ')';
                    }
                    if ('#' === $value[0]) {
                        return $minify_css_color($value);
                    }
                    if (
                        // `1`
                        is_numeric($value) || (
                            // `1px`
                            (
                                '-' === $value[0] || '.' === $value[0] || is_numeric($value[0])
                            ) && preg_match('/^' . $token_css_number . '$/', $value)
                        )
                    ) {
                        // Ensure a space between number(s)
                        return ' ' . $minify_css_value($value) . ' ';
                    }
                    return $value;
                }, $value);
            }, $rules);
            $rules = $every([
                '/\*[\s\S]*?\*/',
                $token_string,
                '[;,/]'
            ], static function($value) {
                if ('/*' === substr($value, 0, 1) && '*/' === substr($value, -2)) {
                    return $value;
                }
                if ('""' === $value || "''" === $value) {
                    return '""';
                }
                if (
                    '"' === $value[0] && '"' === substr($value, -1) ||
                    "'" === $value[0] && "'" === substr($value, -1)
                ) {
                    $raw = substr($value, 1, -1);
                    return false !== strpos($raw, "'") ? '"' . $raw . '"' : "'" . $raw . "'";
                }
                // Misc
                return strtr($value, [
                    '  ' => ' ',
                    ': ' => ':',
                    'font-weight:bold' => 'font-weight:700',
                    'font-weight:normal' => 'font-weight:400',
                    'font:normal ' => 'font:400 '
                ]);
            }, $rules);
            return $selector . '{' . $rules . '}';
        }
        return $value;
    }, $out);
    return $out;
};

$minify_html = function() {};
$minify_js = function() {};
$minify_json = function() {};
$minify_php = function() {};

$state = State::get('x.minify', true);

array_unshift($state['.css'], "");
array_unshift($state['.html'], "");
array_unshift($state['.js'], "");
array_unshift($state['.json'], "");
array_unshift($state['.php'], "");

Minify::_('.css', [$minify_css, $state['.css']]);
Minify::_('.html', [$minify_html, $state['.html']]);
Minify::_('.js', [$minify_js, $state['.js']]);
Minify::_('.json', [$minify_json, $state['.json']]);
Minify::_('.php', [$minify_php, $state['.php']]);