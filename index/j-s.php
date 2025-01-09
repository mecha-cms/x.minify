<?php

namespace x\minify {
    function j_s(?string $from): ?string {
        if ("" === ($from = \trim($from ?? ""))) {
            return null;
        }
        $c1 = '$_ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
        $c2 = '0123456789';
        $c3 = '`"\'/!#%&()*+,-.:;<=>?@[\]^`{|}~'; // Punctuation(s) but `$` and `_`
        $c4 = " \n\r\t";
        $to = "";
        while (false !== ($chop = \strpbrk($from, $c1 . $c2 . $c3 . $c4))) {
            if ("" !== ($v = \strstr($from, $c = $chop[0], true))) {
                $from = $chop;
                $to .= $v;
            }
            // Have to capture the identifier token before the number token. This ensures that identifier(s) containing
            // number(s) will not be compressed by mistake. Valid identifier syntax for JavaScript is actually quite
            // extensive. However, I prefer to stick to a common pattern. If you use some Unicode character(s) in your
            // identifier(s), some thing(s) may not get compressed well. This parser works by looking for the first
            // possible character found in a token (which I have listed in the `$c*` variable), before it continues the
            // step to the end of the token.
            if (false !== \strpos($c1, $c) && \preg_match('/^[a-z$_][\w$]*\b/i', $chop, $m)) {
                $from = \substr($from, \strlen($m[0]));
                if ('false' === $m[0] && '$' !== ($from[0] ?? 0)) {
                    $to = \rtrim($to) . '!1';
                } else if ('true' === $m[0] && '$' !== ($from[0] ?? 0)) {
                    $to = \rtrim($to) . '!0';
                } else {
                    $to .= $m[0];
                }
                continue;
            }
            if (false !== \strpos($c2, $c)) {
                // `0b0`, `0o0`, `0x0`, `0b0n`, `0o0n`, `0x0n`
                if (\preg_match('/^0(b[01]+(_[01]+)*|o[0-7]+(_[0-7]+)?|x[a-f\d]+(_[a-f\d]+)*)n?\b/i', $chop, $m)) {
                    $from = \substr($from, \strlen($m[0]));
                    $to .= \strtr($m[0], ['_' => ""]);
                    continue;
                }
                // `0`, `0n`, `0e0`, `0e+0`, `0e-0`, `0.0`, `0.0e0`, `0.0e+0, `0.0e-0`
                if (\preg_match('/^\d+(_\d+)*(n|(\.\d+(_\d+)*)?(e[+-]?\d+(_\d+)*)?)\b/i', $chop, $m)) {
                    $from = \substr($from, \strlen($m[0]));
                    $v = \strtr($m[0], ['_' => ""]);
                    if (false !== \strpos($v, '.') || '.' === \substr($to, -1)) {
                        $v = \rtrim(\trim($v, '0'), '.');
                    } else {
                        $v = \ltrim($v, '0');
                    }
                    // `return .123` to `return.123`
                    if ("" !== $v && '.' === $v[0] && false === \strpos($c2, \substr(\rtrim($to), -1))) {
                        $to = \rtrim($to);
                    }
                    $to .= "" === $v ? '0' : $v;
                    continue;
                }
            }
            if ($n = \strspn($chop, $c4)) {
                $from = \substr($from, $n);
                // Case of `1 + ++asdf` or `1 - --asdf`
                if (false !== \strpos('+-', $v = \substr($to, -1)) && 2 === \strspn($from, $v)) {
                    $to .= ' ';
                } else if ("" !== $from . $to && false === \strpos($c3, $from[0]) && false === \strpos($c3, $v)) {
                    $to .= ' ';
                }
                continue;
            }
            if (
                // ``…``
                '`' === $c && \preg_match('/^`[^`\\\\]*(?>\\\\.[^`\\\\]*)*`/', $chop, $m) ||
                // `"…"`
                '"' === $c && \preg_match('/^"[^"\\\\]*(?>\\\\.[^"\\\\]*)*"/', $chop, $m) ||
                // `'…'`
                "'" === $c && \preg_match("/^'[^'\\\\]*(?>\\\\.[^'\\\\]*)*'/", $chop, $m)
            ) {
                $from = \substr($from, \strlen($m[0]));
                if ('`' === $c) {
                    if (false !== \strpos($m[0], '${')) {
                        // `${…}`
                        $m[0] = \preg_replace_callback('/\$(\{[^}\\\\]*(?>\\\\.[^}\\\\]*)*\})/', static function ($m) {
                            return j_s($m[0]);
                        }, $m[0]);
                    } else if (false === \strpos($m[0], "\n") && false === \strpos($m[0], "'")) {
                        $m[0] = "'" . \strtr(\substr($m[0], 1, -1), ["\\" . '`' => '`']) . "'";
                    }
                    $to .= $m[0];
                    continue;
                }
                if ('"' === $c && false !== \strpos($m[0], $x = "\\" . '"') && false === \strpos($m[0], "'")) {
                    $to .= "'" . \strtr(\substr($m[0], 1, -1), [$x => '"']) . "'";
                    continue;
                }
                if ("'" === $c && false !== \strpos($m[0], $x = "\\" . "'") && false === \strpos($m[0], '"')) {
                    $to .= '"' . \strtr(\substr($m[0], 1, -1), [$x => "'"]) . '"';
                    continue;
                }
                $to .= $m[0];
                continue;
            }
            if ('/' === $c) {
                $test = $chop[1] ?? 0;
                // `/*…*/`
                if ('*' === $test && \preg_match('/^\/\*[^*]*\*+([^\/*][^*]*\*+)*\//', $chop, $m)) {
                    $from = \substr($from, \strlen($m[0]));
                    // `/*!…*/` or `/**…*/` or <https://en.wikipedia.org/wiki/Conditional_comment>
                    if (false !== \strpos('!*', $m[0][2]) || false !== \strpos($m[0], '@cc_on')) {
                        if (false !== \strpos($m[0], "\n")) {
                            $to .= '/*' . \substr($m[0], 3);
                        } else {
                            $to .= '/*' . \trim(\substr($m[0], 3, -2)) . '*/';
                        }
                    } else if ("" !== $from . $to && false !== \strpos('+-', $v = \substr($to, -1)) && $v === $from[0]) {
                        $to .= ' ';
                    }
                    continue;
                }
                // `//…`
                if ('/' === $test) {
                    $n = \strpos($chop . "\n", "\n") + 1;
                    if (false !== \strpos($v = \substr($from, 0, $n), '@cc_on')) {
                        $from = \substr($from, $n);
                        $to .= '//' . \trim(\substr($v, 2)) . "\n";
                        continue;
                    }
                    $from = \substr($from, $n);
                    continue;
                }
                // `/…/i`
                if (\preg_match('/^\/[^\/\\\\]*(?>\\\\.[^\/\\\\]*)*\/[gimsuy]?\b/', $chop, $m)) {
                    $from = \substr($from, \strlen($m[0]));
                    $to .= $m[0];
                    continue;
                }
                $from = \substr($from, 1);
                $to .= $c;
                continue;
            }
            $from = \substr($from, 1);
            if (false !== \strpos(')]', $c)) {
                $to = \trim($to, ','); // `(a,b,[a,b,c,],)` to `(a,b,[a,b,c])`
            } else if ('}' === $c) {
                $to = \trim($to, ',;'); // `{a;b;c;}` to `{a;b;c}`, `{a:1,b:1,}` to `{a:1,b:1}`
            }
            $to .= $c;
        }
        if ("" !== $from) {
            $to .= $from;
        }
        return "" !== ($to = \trim($to)) ? $to : null;
    }
}