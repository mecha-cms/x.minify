<?php namespace x\minify\_;

\define(__NAMESPACE__ . "\\token_boolean", '\b(?:true|false)\b');
\define(__NAMESPACE__ . "\\token_number", '-?(?:(?:\d+)?\.)?\d+');
\define(__NAMESPACE__ . "\\token_string", '(?:"(?:[^"\\\]|\\\.)*"|\'(?:[^\'\\\]|\\\.)*\')');

\define(__NAMESPACE__ . "\\token_css_combinator", '[~+>]');
\define(__NAMESPACE__ . "\\token_css_comment", '/\*[\s\S]*?\*/');
\define(__NAMESPACE__ . "\\token_css_hack", '[!#$%&()*+,./:<=>?@\[\]_`|~]');
\define(__NAMESPACE__ . "\\token_css_hex", '#(?:[a-f\d]{1,2}){3,4}');
\define(__NAMESPACE__ . "\\token_css_property", '[a-z-][a-z\d-]*');
\define(__NAMESPACE__ . "\\token_css_value", '(?:' . token_string . '|[^;])*');

// <https://www.w3.org/TR/css-values-4>
\define(__NAMESPACE__ . "\\token_css_unit", '%|Hz|Q|cap|ch|cm|deg|dpcm|dpi|dppx|em|ex|grad|ic|in|kHz|lh|mm|ms|pc|pt|px|rad|rcap|rch|rem|rex|ric|rlh|s|turn|vb|vh|vi|vmax|vmin|vw');
\define(__NAMESPACE__ . "\\token_css_number", token_number . '(?:' . token_css_unit . ')');

function every(array $tokens, callable $fn = null, string $in = null, string $flag = 'i') {
    if ("" === ($in = \trim($in))) {
        return "";
    }
    $pattern = \strtr('(?:' . \implode(')|(?:', $tokens) . ')', ['/' => "\\/"]);
    $chops = \preg_split('/(' . $pattern . ')/' . $flag, $in, null, \PREG_SPLIT_DELIM_CAPTURE | \PREG_SPLIT_NO_EMPTY);
    if (!$fn) {
        return $chops;
    }
    $out = "";
    while ($chops) {
        $chop = \array_shift($chops);
        if ("" === ($value = \trim($chop))) {
            continue;
        }
        $out .= $fn($value, $chop);
    }
    return $out;
}

function get_css_selector_rules($value) {
    $rules = $selector = "";
    $m = \preg_split('/(' . token_string . '|[{])/', $value, null, \PREG_SPLIT_DELIM_CAPTURE | \PREG_SPLIT_NO_EMPTY);
    while ($m) {
        if ('{' === ($v = \array_shift($m))) {
            break;
        }
        $selector .= $v;
    }
    $selector = \trim($selector);
    $rules = \trim(\substr(\implode("", $m), 0, -1), ' ;');
    return [$selector, $rules];
}

function get_token_css_function($value) {
    if (')' === \substr($value, -1) && \strpos($value, '(') > 0) {
        if (\preg_match('/^(' . token_css_property . ')\(\s*([\s\S]*)\s*\)$/', $value, $m)) {
            \array_shift($m);
            return $m;
        }
    }
    return false;
}

function is_token_number($value) {
    return \is_numeric($value);
}

function is_token_string($value) {
    return ('"' === $value[0] && '"' === \substr($value, -1) || "'" === $value[0] && "'" === \substr($value, -1));
}

function is_token_css_comment($value) {
    return '/*' === \substr($value, 0, 2) && '*/' === \substr($value, -2);
}

function is_token_css_hex($value) {
    return '#' === $value[0];
}

function is_token_css_unit($value) {
    return (
        // `1`
        \is_numeric($value) || (
            // `1px`
            (
                '-' === $value[0] || '.' === $value[0] || \is_numeric($value[0])
            ) && \preg_match('/^' . token_css_number . '$/', $value)
        )
    );
}

function minify_number($value) {
    if ('-' === $value[0] && \strlen($value) > 1) {
        $number = minify_number(\substr($value, 1));
        return '0' === $number ? '0' : '-' . $number;
    }
    if (\is_numeric($value)) {
        if (false !== \strpos($value, '.')) {
            // Convert `5.0` to `5.`
            // Convert `5.10` to `5.1`
            $value = \rtrim($value, '0');
            // Convert `5.` to `5`
            $value = \rtrim($value, '.');
        }
        // Convert `0.5` to `.5`
        return ('0.' === \substr($value, 0, 2) ? \substr($value, 1) : $value);
    }
    return $value;
}

function minify_css_color($value) {
    if ('#' === $value[0]) {
        $value = \strtolower(\preg_replace('/^#([a-f\d])\1([a-f\d])\2([a-f\d])\3(?:([a-f\d])\4)?$/i', '#$1$2$3$4', $value));
        if (9 === \strlen($value) && 'ff' === \substr($value, -2)) {
            return \substr($value, 0, -2); // Solid HEX color
        }
        if (5 === \strlen($value) && 'f' === \substr($value, -1)) {
            return \substr($value, 0, -1); // Solid HEX color
        }
        return $value;
    }
    $value = \preg_replace('/\s*([(),\/])\s*/', '$1', $value);
    if (0 === \strpos($value, 'rgba(')) {
        if (',1)' === \substr($value, -3) || '/1)' === \substr($value, -3)) {
            // Solid alpha channel, convert it to RGB
            $hex = minify_css_color($rgb = 'rgb(' . \substr($value, 5, -3) . ')');
            return \strlen($hex) < \strlen($rgb) ? $hex : $rgb;
        }
        if (\preg_match('/^rgba\((\d+)[, ](\d+)[, ](\d+)[,\/](' . token_number . ')\)$/', $value, $m)) {
            $hex = minify_css_color(\sprintf("#%02x%02x%02x%02x", $m[1], $m[2], $m[3], ((float) $m[4]) * 255));
            return \strlen($hex) < \strlen($value) ? $hex : $value;
        }
    }
    if (0 === \strpos($value, 'rgb(')) {
        if (\preg_match('/^rgb\((\d+)[, ](\d+)[, ](\d+)\)$/', $value, $m)) {
            $hex = minify_css_color(\sprintf("#%02x%02x%02x", $m[1], $m[2], $m[3]));
            return \strlen($hex) < \strlen($value) ? $hex : $value;
        }
    }
    return $value;
}

function minify_css_unit($value) {
    $number = $value;
    $unit = "";
    if (!\is_numeric($value) && \preg_match('/^(' . token_number . ')(' . token_css_property . '|%)$/i', $value, $m)) {
        $number = $m[1];
        $unit = $m[2];
    }
    $number = minify_number($number);
    if ('0' === $number) {
        // `0%` or `0deg`
        if ('%' === $unit || 'deg' === $unit) {
            return $number . $unit;
        }
        return $number;
    }
    return $number . $unit;
}

function minify_css(string $in, int $comment = 2, int $quote = 2) {
    if ("" === ($in = \trim($in))) {
        return "";
    }
    $out = every([token_css_comment], static function($value) use($comment) {
        if (1 === $comment) {
            return $value;
        }
        if (is_token_css_comment($value)) {
            if (2 === $comment) {
                if (
                    // Detect special comment(s) from the third character.
                    // Should be an `!` or `*` → `/*! asdf */` or `/** asdf */`
                    isset($value[2]) && false !== \strpos('!*', $value[2]) ||
                    // Detect license comment(s) from the content phrase.
                    // It should contains character(s) like `@license`
                    false !== \strpos($value, '@licence') || // noun
                    false !== \strpos($value, '@license') || // verb
                    false !== \strpos($value, '@preserve')
                ) {
                    return $value;
                }
            }
            return ""; // Remove!
        }
        return $value;
    }, $in);
    $out = every([
        token_css_comment,
        // Match `@asdf` until `;`
        '@' . token_css_property . '\s+(?:' . token_string . '|[^{};])+\s*;',
        // Match selector and rule(s)
        '@?(?:' . token_string . '|[^@{};])+\{\s*(?:' . token_string . '|[^{}]|(?R))*\s*\}'
    ], static function($value) {
        if (is_token_css_comment($value)) {
            return $value; // Keep!
        }
        // Normalize white-space(s) to a space
        $value = \preg_replace('/\s+/', ' ', $value);
        // Remove empty selector and rule(s)
        if ('{}' === \substr($value, -2) || '{ }' === \substr($value, -3)) {
            return "";
        }
        if ('@' === $value[0]) {
            if (';' === \substr($value, -1)) {
                $m = \explode(' ', $value, 2);
                return $m[0] . ' ' . \substr(minify_css('x{x:' . $m[1] . '}'), 4, -1) . ';';
            }
            list($selector, $rules) = get_css_selector_rules($value);
            if ('@font-face' === $selector) {
                return $selector . \substr(minify_css('x{' . $rules . '}'), 1);
            }
            $selector = \preg_replace_callback('/\(\s*((?:' . token_string . '|[^()]|(?R))*)\s*\)/', static function($m) {
                return '(' . \substr(minify_css('x{' . $m[1] . '}'), 2, -1) . ')';
            }, $selector);
            return $selector . '{' . minify_css($rules) . '}';
        }
        if ('}' === \substr($value, -1)) {
            list($selector, $rules) = get_css_selector_rules($value);
            $selector = every([
                // Match nesting and global selector(s)
                '\s*[&*]\s*',
                // Match function-like pseudo class/element selector(s)
                '\s*:{1,2}' . token_css_property . '\((?:' . token_string . '|[^()]|(?R))*\)\s*',
                // Match pseudo class/element selector(s)
                '\s*:{1,2}' . token_css_property . '\s*',
                // Match attribute selector(s)
                '\s*\[\s*(?:' . token_string . '|[^][]|(?R))*\s*\]\s*'
            ], static function($value, $chop) {
                if (':' === $value[0]) {
                    if (')' === \substr($value, -1)) {
                        return \preg_replace_callback('/\(\s*((?:' . token_string . '|[^()]|(?R))*)\s*\)/', static function($m) {
                            // Assume that argument(s) of `:foo()` is a complete CSS declaration so we can minify it recursively.
                            // FYI, that `{x:x}` part is a dummy just to make sure that the minifier will not remove the whole rule(s)
                            // since `x{x:x}` is not considered as a selector with empty rule(s).
                            return '(' . \substr(minify_css($m[1] . '{x:x}'), 0, -5) . ')';
                        }, $chop);
                    }
                    return $chop;
                }
                if ('[' === $value[0] && ']' === \substr($value, -1)) {
                    return \preg_replace_callback('/=(' . token_string . ')(?:\s*([is]))?(?=\])/i', static function($m) {
                        $value = \substr($m[1], 1, -1);
                        if ("" === $value) {
                            return '=""';
                        }
                        // <https://mothereff.in/unquoted-attributes>
                        if (\ctype_alpha($value[0]) && \preg_match('/^' . token_css_property . '$/i', $value)) {
                            return '=' . $value . (isset($m[2]) ? ' ' . $m[2] : "");
                        }
                        if (false !== \strpos($value, "'")) {
                            return '="' . $value . '"' . ($m[2] ?? "");
                        }
                        return "='" . $value . "'" . ($m[2] ?? "");
                    }, $chop);
                }
                return $chop;
            }, $selector);
            $selector = every([token_string, token_css_combinator, '[,]'], static function($value) {
                return $value;
            }, $selector);
            $rules = every([token_css_comment, '[;]'], static function($value) {
                if (is_token_css_comment($value)) {
                    return $value;
                }
                // Minify shorthand value(s)
                if (\preg_match('/^(' . \strtr(token_css_hack, ['/' => "\\/"]) . '?)(background|border(?:-(?:width|radius))?|margin|padding|outline)\s*:\s*(' . token_css_value . ')\s*$/i', $value, $m)) {
                    $hack = $m[1];
                    $property = $m[2];
                    $v = $m[3];
                    if ('background' === $property && ('none' === $v || 'transparent' === $v)) {
                        return $hack . $property . ':0 0';
                    }
                    if ('none' === $v) {
                        return $hack . $property . ':0';
                    }
                    $unit = '(' . token_css_number . '|' . token_number . ')';
                    // `1px 0 1px 0`
                    if (\preg_match('/^' . $unit . ' ' . $unit . ' ' . $unit . ' ' . $unit . '$/i', $v, $m)) {
                        $m[1] = minify_css_unit($m[1]);
                        $m[2] = minify_css_unit($m[2]);
                        $m[3] = minify_css_unit($m[3]);
                        $m[4] = minify_css_unit($m[4]);
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
                    if (\preg_match('/^' . $unit . ' ' . $unit . ' ' . $unit . '$/i', $v, $m)) {
                        $m[1] = minify_css_unit($m[1]);
                        $m[2] = minify_css_unit($m[2]);
                        $m[3] = minify_css_unit($m[3]);
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
                    if (\preg_match('/^' . $unit . ' ' . $unit . '$/i', $v, $m)) {
                        $m[1] = minify_css_unit($m[1]);
                        $m[2] = minify_css_unit($m[2]);
                        if ($m[1] === $m[2]) {
                            // `1px`
                            return $hack . $property . ':' . $m[1];
                        }
                    }
                    // `1px`
                    if (\preg_match('/^' . $unit . '$/i', $v, $m)) {
                        $m[1] = minify_css_unit($m[1]);
                        return $hack . $property . ':' . $m[1];
                    }
                    return $hack . $property . ':' . $v;
                }
                return $value;
            }, $rules);
            $rules = every([
                token_css_comment,
                // Match property
                // FYI, that `(?<=^|;)` part was added to make sure that property comes
                // at the beginning of the chunk, or just after the `;` character.
                // I need to make sure that `http:` will not be captured as a property.
                '(?<=^|;)' . token_css_hack . '?' . token_css_property . '\s*:\s*'
                // ... other must be the value
            ], static function($value) {
                if (is_token_css_comment($value)) {
                    return $value;
                }
                // `margin:`
                if (':' === \substr($value, -1)) {
                    return \strtr($value, [' ' => ""]);
                }
                // Other(s)
                return every([
                    // Match function-like which contains only string
                    token_css_property . '\(\s*' . token_string . '\s*\)',
                    // Match function-like which may contains string
                    token_css_property . '\(\s*(?:' . token_string . '|[^;])*\s*\)',
                    token_string,
                    token_css_hex,
                    token_css_number,
                    token_number
                ], static function($value) {
                    // `format('woff')`
                    if ($m = get_token_css_function($value)) {
                        $name = $m[0];
                        $params = $m[1];
                        // Prepare to remove quote(s) from string-only argument in function
                        if ($params && is_token_string($params)) {
                            $raw = \substr($params, 1, -1);
                        }
                        if ('calc' === $name) {
                            // Only minify the number, do not remove the unit for now. I have no idea how
                            // this `calc()` thing works in handling the unit(s). As far as I know, the only
                            // valid unit-less number is when they are used as the divisor/multiplicator.
                            // We can remove the space around `*`, `(`, `)` and `/` character safely.
                            $params = \preg_replace_callback('/(' . token_number . '|\s*[*(),\/]\s*)/', static function($m) {
                                return \is_numeric($m[1]) ? minify_number($m[1]) : \trim($m[1]);
                            }, $params);
                            return 'calc(' . $params . ')';
                        }
                        if ('format' === $name && "" !== $raw) {
                            // Cannot remove quote(s) in `format()` safely :(
                            return "format('" . $raw . "')";
                        }
                        if ('url' === $name && "" !== $raw) {
                            // Only remove quote(s) around URL if it does not contain space character(s)
                            return false !== \strpos($raw, ' ') ? "url('" . $raw . "')" : 'url(' . $raw . ')';
                        }
                        // <https://www.w3.org/TR/css-color-4>
                        if (false !== \strpos(',color,device-cmyk,hsl,hsla,hwb,lab,lch,rgb,rgba,', ',' . $name . ',')) {
                            return minify_css_color($value);
                        }
                        return $name . '(' . $params . ')';
                    }
                    if (is_token_css_hex($value)) {
                        return minify_css_color($value);
                    }
                    if (is_token_css_unit($value)) {
                        // Ensure a space between number(s)
                        return ' ' . minify_css_unit($value) . ' ';
                    }
                    return $value;
                }, $value);
            }, $rules);
            $rules = every([token_css_comment, token_string, '[;,/]'], static function($value) {
                if (is_token_css_comment($value)) {
                    return $value;
                }
                if ('""' === $value || "''" === $value) {
                    return '""';
                }
                if (is_token_string($value)) {
                    $raw = \substr($value, 1, -1);
                    return false !== \strpos($raw, "'") ? '"' . $raw . '"' : "'" . $raw . "'";
                }
                // Misc
                return \strtr($value, [
                    '  ' => ' ',
                    ': ' => ':',
                    'font-weight:bold' => 'font-weight:700',
                    'font-weight:normal' => 'font-weight:400',
                    'font:normal normal ' => 'font:',
                    'font:normal ' => 'font:400 '
                ]);
            }, $rules);
            return $selector . '{' . $rules . '}';
        }
        return $value;
    }, $out);
    return $out;
}

function minify_html(string $in, int $comment = 2, int $quote = 1) {}

function minify_js(string $in, int $comment = 2, int $quote = 1) {}

function minify_json(string $in, int $comment = 2, int $quote = 1) {
    return \json_encode(\json_decode($in), \JSON_UNESCAPED_UNICODE);
}

// Based on <https://php.net/manual/en/function.php-strip-whitespace.php#82437>
function minify_php(string $in, int $comment = 2, int $quote = 1) {
    $out = "";
    $t = [];
    // White-space(s) around these token(s) can be removed
    foreach ([
        'AND_EQUAL',
        'ARRAY_CAST',
        'BOOLEAN_AND',
        'BOOLEAN_OR',
        'BOOL_CAST',
        'COALESCE',
        'CONCAT_EQUAL',
        'DEC',
        'DIV_EQUAL',
        'DOLLAR_OPEN_CURLY_BRACES',
        'DOUBLE_ARROW',
        'DOUBLE_CAST',
        'DOUBLE_COLON',
        'INC',
        'INT_CAST',
        'IS_EQUAL',
        'IS_GREATER_OR_EQUAL',
        'IS_IDENTICAL',
        'IS_NOT_EQUAL',
        'IS_NOT_IDENTICAL',
        'IS_SMALLER_OR_EQUAL',
        'MINUS_EQUAL',
        'MOD_EQUAL',
        'MUL_EQUAL',
        'OBJECT_OPERATOR',
        'OR_EQUAL',
        'PAAMAYIM_NEKUDOTAYIM',
        'PLUS_EQUAL',
        'POW',
        'POW_EQUAL',
        'SL',
        'SL_EQUAL',
        'SPACESHIP',
        'SR',
        'SR_EQUAL',
        'STRING_CAST',
        'XOR_EQUAL'
    ] as $v) {
        if (\defined($v = "\\T_" . $v)) {
            $t[\constant($v)] = 1;
        }
    }
    $c = \count($toks = \token_get_all($in));
    $doc = $skip = false;
    $begin = $end = null;
    for ($i = 0; $i < $c; ++$i) {
        $tok = $toks[$i];
        if (\is_array($tok)) {
            $id = $tok[0];
            $value = $tok[1];
            if (\T_INLINE_HTML === $id) {
                $out .= $value;
                $skip = false;
            } else {
                if (\T_OPEN_TAG === $id) {
                    if (
                        false !== \strpos($value, ' ') ||
                        false !== \strpos($value, "\n") ||
                        false !== \strpos($value, "\t") ||
                        false !== \strpos($value, "\r")
                    ) {
                        $value = \rtrim($value);
                    }
                    $out .= $value . ' ';
                    $begin = \T_OPEN_TAG;
                    $skip = true;
                } else if (\T_OPEN_TAG_WITH_ECHO === $id) {
                    $out .= $value;
                    $begin = \T_OPEN_TAG_WITH_ECHO;
                    $skip = true;
                } else if (\T_CLOSE_TAG === $id) {
                    if (\T_OPEN_TAG_WITH_ECHO === $begin) {
                        $out = \rtrim($out, '; ');
                    } else {
                        $value = ' ' . $value;
                    }
                    $out .= \trim($value);
                    $begin = null;
                    $skip = false;
                } else if (isset($t[$id])) {
                    $out .= $value;
                    $skip = true;
                } else if (\T_ENCAPSED_AND_WHITESPACE === $id || \T_CONSTANT_ENCAPSED_STRING === $id) {
                    if ('"' === $value[0]) {
                        $value = \addcslashes($value, "\n\r\t");
                    }
                    $out .= $value;
                    $skip = true;
                } else if (\T_WHITESPACE === $id) {
                    $n = $toks[$i + 1] ?? null;
                    if(!$skip && (!\is_string($n) || '$' === $n) && !isset($t[$n[0]])) {
                        $out .= ' ';
                    }
                    $skip = false;
                } else if (\T_START_HEREDOC === $id) {
                    $out .= "<<<S\n";
                    $skip = false;
                    $doc = true; // Enter HEREDOC
                } else if (\T_END_HEREDOC === $id) {
                    $out .= "S\n";
                    $skip = true;
                    $doc = false; // Exit HEREDOC
                    for ($j = $i + 1; $j < $c; ++$j) {
                        if (\is_string($toks[$j])) {
                            $out .= $toks[$j];
                            if (';' === $toks[$j]) {
                                if ("\nS\n;" === \substr($out, -4)) {
                                    $out = \rtrim($out, "\n;") . ";\n";
                                }
                                $i = $j;
                                break;
                            }
                        } else if (\T_CLOSE_TAG === $toks[$j][0]) {
                            break;
                        }
                    }
                } else if (\T_COMMENT === $id || \T_DOC_COMMENT === $id) {
                    if (
                        1 === $comment || (
                            2 === $comment && (
                                // Detect special comment(s) from the third character
                                // It should be a `!` or `*` → `/*! keep */` or `/** keep */`
                                !empty($value[2]) && false !== \strpos('!*', $value[2]) ||
                                // Detect license comment(s) from the content
                                // It should contains character(s) like `@license`
                                false !== \strpos($value, '@licence') || // noun
                                false !== \strpos($value, '@license') || // verb
                                false !== \strpos($value, '@preserve')
                            )
                        )
                    ) {
                        $out .= $value;
                    }
                    $skip = true;
                } else {
                    $out .= $value;
                    $skip = false;
                }
            }
            $end = "";
        } else {
            if (false === \strpos(';:', $tok) || $end !== $tok) {
                $out .= $tok;
                $end = $tok;
            }
            $skip = true;
        }
    }
    return $out;
}

$state = \State::get('x.minify', true);

\array_unshift($state['.css'], "");
\array_unshift($state['.html'], "");
\array_unshift($state['.js'], "");
\array_unshift($state['.json'], "");
\array_unshift($state['.php'], "");

\Minify::_('.css', [__NAMESPACE__ . "\\minify_css", $state['.css']]);
\Minify::_('.html', [__NAMESPACE__ . "\\minify_html", $state['.html']]);
\Minify::_('.js', [__NAMESPACE__ . "\\minify_js", $state['.js']]);
\Minify::_('.json', [__NAMESPACE__ . "\\minify_json", $state['.json']]);
\Minify::_('.php', [__NAMESPACE__ . "\\minify_php", $state['.php']]);