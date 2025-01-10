<?php

namespace x\minify {
    function p_h_p(?string $from): ?string {
        if ("" === ($from = \trim($from ?? ""))) {
            return null;
        }
        $count = \count($lot = \token_get_all($from));
        $in_array = $is_array = 0;
        $to = "";
        foreach ($lot as $k => $v) {
            if ('stdclass' === \strtolower(\substr($to, -8)) && \preg_match('/\bnew \\\\?stdclass$/i', $to, $m)) {
                $to = \trim(\substr($to, 0, -\strlen($m[0]))) . '(object)[]';
            }
            if (\is_array($v)) {
                // Can be `array $asdf` or `array (`
                if (\T_ARRAY === $v[0]) {
                    $i = $k + 1;
                    while (isset($lot[$i])) {
                        if (\is_array($lot[$i]) && \T_WHITESPACE !== $lot[$i][0]) {
                            break;
                        }
                        if (\is_string($lot[$i])) {
                            if ('(' === $lot[$i]) {
                                $is_array += 1;
                            }
                            break;
                        }
                        ++$i;
                    }
                    if (!$is_array) {
                        $to .= $v[1];
                    }
                    continue;
                }
                if ('_CAST' === \substr(\token_name($v[0]), -5)) {
                    $cast = \trim(\substr($v[1], 1, -1));
                    if ('boolean' === $cast) {
                        $cast = 'bool';
                    } else if ('double' === $cast || 'real' === $cast) {
                        $cast = 'float';
                    } else if ('integer' === $cast) {
                        $cast = 'int';
                    }
                    $to .= '(' . $cast . ')';
                    continue;
                }
                if (\T_CLOSE_TAG === $v[0]) {
                    if ($k === $count - 1) {
                        $to = \trim($to, ';') . ';';
                        continue;
                    }
                    // <https://www.php.net/language.basic-syntax.instruction-separation>
                    $to = \trim(\trim($to, ';')) . $v[1];
                    continue;
                }
                if (\T_COMMENT === $v[0] || \T_DOC_COMMENT === $v[0]) {
                    if (0 === \strpos($v[1], '/*') && false !== \strpos('!*', $v[1][2])) {
                        if (false !== \strpos($v[1], "\n")) {
                            $to .= '/*' . \substr($v[1], 3);
                        } else {
                            $to .= '/*' . \trim(\substr($v[1], 3, -2)) . '*/';
                        }
                    }
                    continue;
                }
                if (\T_CONSTANT_ENCAPSED_STRING === $v[0]) {
                    if ('(binary)' === \substr($to, -8)) {
                        $to = \substr($to, 0, -8) . 'b';
                    }
                    $to = \trim($to) . $v[1];
                    continue;
                }
                if (\T_DNUMBER === $v[0]) {
                    $test = \strtolower(\rtrim(\trim(\strtr($v[1], ['_' => ""]), '0'), '.'));
                    if (false === \strpos($test = "" !== $test ? $test : '0', '.')) {
                        if (false === \strpos($test, 'e')) {
                            $test .= '.0';
                        }
                    }
                    if ('(int)' === \substr($to, -5)) {
                        $to = \substr($to, 0, -5) . \var_export((int) $test, true);
                        continue;
                    }
                    if ('(string)' === \substr($to, -8)) {
                        $to = \substr($to, 0, -8) . "'" . $test . "'";
                        continue;
                    }
                    $to .= $test;
                    continue;
                }
                if (\T_ECHO === $v[0] || \T_PRINT === $v[0]) {
                    if ('<?php ' === \substr($to, -6)) {
                        // Replace `<?php echo` with `<?=`
                        $to = \substr($to, 0, -4) . '=';
                        continue;
                    }
                    // Replace `print` with `echo`
                    $to .= 'echo ';
                    continue;
                }
                if (\T_ENCAPSED_AND_WHITESPACE === $v[0]) {
                    $v[1] = \strtr($v[1], ["S\n" => "\\x53\n"]);
                    // `asdf { $asdf } asdf`
                    if ('}' === (\trim($v[1])[0] ?? 0) && false !== ($test = \strrchr($to, '{'))) {
                        $to = \substr($to, 0, -\strlen($test)) . '{' . \trim(\substr($test, 1)) . \trim($v[1]);
                        continue;
                    }
                    $to .= $v[1] . (false !== \strpos(" \n\r\t", \substr($v[1], -1)) ? "\x1a" : "");
                    continue;
                }
                if (\T_END_HEREDOC === $v[0]) {
                    $to .= 'S';
                    continue;
                }
                if (\T_INLINE_HTML === $v[0]) {
                    $to .= $v[1];
                    continue;
                }
                if (\T_LNUMBER === $v[0]) {
                    $test = \strtolower(\ltrim(\strtr($v[1], ['_' => ""]), '0'));
                    if ('(float)' === \substr($to, -7)) {
                        $to = \substr($to, 0, -7) . \var_export((float) $test, true);
                        continue;
                    }
                    $test = "" !== $test ? $test : '0';
                    if ('(string)' === \substr($to, -8)) {
                        $to = \substr($to, 0, -8) . "'" . $test . "'";
                        continue;
                    }
                    $to .= $test;
                    continue;
                }
                if (\T_OPEN_TAG === $v[0]) {
                    $to .= \trim($v[1]) . ' ';
                    continue;
                }
                if (\T_OPEN_TAG_WITH_ECHO === $v[0]) {
                    $to .= $v[1];
                    continue;
                }
                if (\T_START_HEREDOC === $v[0]) {
                    if ("'" === $v[1][3]) {
                        $to .= "<<<'S'\n";
                        continue;
                    }
                    $to .= "<<<S\n";
                    continue;
                }
                if (\T_STRING === $v[0]) {
                    $test = \strtolower($v[1]);
                    if ('false' === $test) {
                        $to = \trim($to) . '!1';
                    } else if ('null' === $test) {
                        $to .= $test;
                    } else if ('true' === $test) {
                        $to = \trim($to) . '!0';
                    } else {
                        $to .= $v[1];
                    }
                    continue;
                }
                // <https://stackoverflow.com/a/16606419/1163000>
                if (\T_VARIABLE === $v[0]) {
                    if ('(bool)' === \substr($to, -6)) {
                        $to = \substr($to, 0, -6) . '!!' . $v[1];
                    } else if ('(float)' === \substr($to, -7)) {
                        $to = \substr($to, 0, -7) . $v[1] . '+0';
                    } else if ('(int)' === \substr($to, -5)) {
                        $to = \substr($to, 0, -5) . $v[1] . '+0';
                    } else if ('(string)' === \substr($to, -8)) {
                        $to = \substr($to, 0, -8) . $v[1] . '.""';
                    } else if ("\x1a" === \substr($to, -1)) {
                        $to = \substr($to, 0, -1) . $v[1];
                    } else {
                        if ('<?php ' === \substr($to, -6)) {
                            $to .= $v[1];
                        } else {
                            $to = \trim($to) . $v[1];
                        }
                    }
                    continue;
                }
                if (\T_WHITESPACE === $v[0]) {
                    $to .= false !== \strpos(' "/!#%&()*+,-.:;<=>?@[\]^`{|}~' . "'", \substr($to, -1)) ? "" : ' ';
                    continue;
                }
                // Math operator(s)
                if (false !== \strpos('!%&*+-./<=>?|~', $v[1][0])) {
                    $to = \trim($to) . $v[1];
                    continue;
                }
                $to .= $v[1];
                continue;
            }
            if ($is_array && '(' === $v) {
                $in_array += 1;
                $to = \trim($to) . '[';
                continue;
            }
            if ($is_array && ')' === $v) {
                if ($in_array === $is_array) {
                    $in_array -= 1;
                    $is_array -= 1;
                    $to = \trim(\trim($to, ',')) . ']';
                    continue;
                }
            }
            if (false !== \strpos('([', $v)) {
                $to = \trim($to) . $v;
                continue;
            }
            if (false !== \strpos(')]', $v)) {
                // `new stdclass()` to `(object)[]()` to `(object)[]`
                if ('(object)[](' === \substr($to, -11)) {
                    $to = \substr($to, 0, -1);
                    continue;
                }
                $to = \trim(\trim($to, ',')) . $v;
                continue;
            }
            $to = \trim($to) . $v;
        }
        return "" !== ($to = \trim(\strtr($to, ["\x1a" => ""]))) ? $to : null;
    }
}