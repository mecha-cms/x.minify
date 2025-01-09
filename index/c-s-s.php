<?php

namespace x\minify {
    function c_s_s(?string $from): ?string {
        if ("" === ($from = \trim($from ?? ""))) {
            return null;
        }
        $c1 = '-_ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
        $c2 = '0123456789';
        $c3 = '"\'/[!#()+,:;<>{}~';
        $c4 = " \n\r\t";
        $r1 = '"[^"\\\\]*(?>\\\\.[^"\\\\]*)*"';
        $r2 = "'[^'\\\\]*(?>\\\\.[^'\\\\]*)*'";
        $r3 = $r1 . '|' . $r2;
        $to = "";
        while (false !== ($chop = \strpbrk($from, $c1 . $c2 . $c3 . $c4))) {
            if ("" !== ($v = \strstr($from, $c = $chop[0], true))) {
                $from = $chop;
                $to .= $v;
            }
            // <https://www.w3.org/TR/css-color-4#the-hsl-notation>
            if ('h' === $c && (0 === \strpos($chop, 'hsl(') || 0 === \strpos($chop, 'hsla(')) && \preg_match('/^hsla?\(\s*(none|\d*\.?\d+(?:deg)?)\s*[,\s]\s*(none|\d*\.?\d+%?)\s*[,\s]\s*(none|\d*\.?\d+%?)\s*(?:[,\/]\s*(none|\d*\.?\d+%?)\s*)?\)/', $chop, $m)) {
                $from = \substr($from, \strlen($m[0]));
                $h = 'none' === $m[1] ? 0 : (float) ('deg' === \substr($m[1], -3) ? \substr($m[1], 0, -3) : $m[1]);
                $s = 'none' === $m[2] ? 0 : (float) ('%' === \substr($m[2], -1) ? \substr($m[2], 0, -1) : $m[2]);
                $l = 'none' === $m[3] ? 0 : (float) ('%' === \substr($m[3], -1) ? \substr($m[3], 0, -1) : $m[3]);
                $a = isset($m[4]) ? ('none' === $m[4] ? 0 : (float) ('%' === \substr($m[4], -1) ? \substr($m[4], 0, -1) : $m[4])) : 1;
                // <https://www.w3.org/TR/css-color-4#hsl-to-rgb>
                $h = $h % 360;
                if ($h < 0) {
                    $h += 360;
                }
                $s /= 100;
                $l /= 100;
                $f = static function ($n) use ($h, $l, $s) {
                    $a = $s * \min($l, 1 - $l);
                    $k = ($n + $h / 30) % 12;
                    return $l - $a * \max(-1, \min($k - 3, 9 - $k, 1));
                };
                $to .= c_s_s(\sprintf('#%02x%02x%02x%02x', $f(0) * 255, $f(8) * 255, $f(4) * 255, $a * 255));
                continue;
            }
            // <https://www.w3.org/TR/css-color-4#rgb-functions>
            if ('r' === $c && (0 === \strpos($chop, 'rgb(') || 0 === \strpos($chop, 'rgba(')) && \preg_match('/^rgba?\(\s*(none|\d*\.?\d+%?)\s*[,\s]\s*(none|\d*\.?\d+%?)\s*[,\s]\s*(none|\d*\.?\d+%?)\s*(?:[,\/]\s*(none|\d*\.?\d+%?)\s*)?\)/', $chop, $m)) {
                $from = \substr($from, \strlen($m[0]));
                $r = 'none' === $m[1] ? 0 : ('%' === \substr($m[1], -1) ? (255 * (((float) \substr($m[1], 0, -1)) / 100)) : (float) $m[1]);
                $g = 'none' === $m[2] ? 0 : ('%' === \substr($m[2], -1) ? (255 * (((float) \substr($m[2], 0, -1)) / 100)) : (float) $m[2]);
                $b = 'none' === $m[3] ? 0 : ('%' === \substr($m[3], -1) ? (255 * (((float) \substr($m[3], 0, -1)) / 100)) : (float) $m[3]);
                $a = (isset($m[4]) ? ('none' === $m[4] ? 0 : ('%' === \substr($m[4], -1) ? (1 * (((float) \substr($m[4], 0, -1)) / 100)) : (float) $m[4])) : 1) * 255;
                $x = \sprintf('#%02x%02x%02x%02x', $r < 0 ? 0 : ($r > 255 ? 255 : $r), $g < 0 ? 0 : ($g > 255 ? 255 : $g), $b < 0 ? 0 : ($b > 255 ? 255 : $b), $a < 0 ? 0 : ($a > 255 ? 255 : $a));
                $to .= c_s_s($x);
                continue;
            }
            // <https://www.w3.org/TR/css-values-4#numeric-types>
            if (false !== \strpos($c1, $c) && '0' === \substr($to, -1) && \preg_match('/[*+,\/\s:-]0$/', $to) && \preg_match('/^(?>Hz|Q|cap|ch|cm|dpcm|dpi|dppx|em|ex|grad|ic|in|kHz|lh|mm|ms|pc|pt|px|rad|rcap|rch|rem|rex|ric|rlh|s|turn|vb|vh|vi|vmax|vmin|vw)\b/', $chop, $m)) {
                // Remove unit after zero value except `%` and `deg`
                $from = \substr($from, \strlen($m[0]));
                continue;
            }
            // <https://www.w3.org/TR/css-syntax-3#ident-token-diagram>
            if (false !== \strpos("\\" . $c1, $c) && \preg_match('/^(?>\\\\[a-f\d]+\s+|\\\\.|[a-z_-])(?>\\\\[a-f\d]+\s+|\\\\.|[a-z\d_-])*/i', $chop, $m)) {
                $from = \substr($from, \strlen($m[0]));
                // <https://www.w3.org/TR/css-values-4#calc-syntax>
                if (0 === \strpos($from, '(') && false !== \strpos(',abs,acos,asin,atan,atan2,calc,clamp,cos,exp,hypot,log,max,min,mod,pow,rem,round,sign,sin,sqrt,tan,', ',' . $m[0] . ',') && \preg_match('/^\([^;}]+\)/', $from, $n)) {
                    $from = \substr($from, \strlen($n[0]));
                    // Remove space(s) around `*` and `/`
                    $to .= $m[0] . \preg_replace('/\s*([*\/])\s*/', '$1', c_s_s($n[0]));
                    continue;
                }
                $to .= \preg_replace('/\s+/', ' ', $m[0]);
                continue;
            }
            // <https://www.w3.org/TR/css-syntax-3#number-token-diagram>
            if (false !== \strpos($c2, $c)) {
                if (\preg_match('/^\d+(\.\d+)?(e[+-]?\d+)?\b/i', $chop, $m)) {
                    $from = \substr($from, \strlen($m[0]));
                    if (false !== \strpos($v = $m[0], '.') || '.' === \substr($to, -1)) {
                        $v = \rtrim(\trim($v, '0'), '.');
                    } else {
                        $v = \ltrim($v, '0');
                    }
                    $to .= "" === $v ? '0' : $v;
                    continue;
                }
            }
            if ($n = \strspn($chop, $c4)) {
                $from = \substr($from, $n);
                $a = $from[0] ?? "";
                $b = \substr($to, -1);
                if (\strlen($from) > 1 && ('[' === $a || ':' === $a && false === \strpos($c4, $from[1]))) {
                    if ("" !== $b && false === \strpos('+,>}', $b)) {
                        $to .= ' '; // Case of `asdf :asdf` and `asdf [asdf]`
                    }
                } else if (
                    // Case of `@asdf "asdf"` and `"asdf" asdf` or `@asdf (asdf)` and `(asdf) asdf`
                    false !== \strpos('"\'(', $a) && false === \strpos($c3, $b) ||
                    false !== \strpos('"\')', $b) && false === \strpos($c3, $a)
                ) {
                    $to .= ' ';
                } else if ("" !== $a . $b && false === \strpos($c3, $a) && false === \strpos($c3, $b)) {
                    $to .= ' ';
                }
                continue;
            }
            if (false !== \strpos('"\'', $c) && \preg_match('/^(?>' . $r3 . ')/', $chop, $m)) {
                $from = \substr($from, \strlen($m[0]));
                if ('format(' === \substr($to, -7)) {
                    $test = \trim(\substr($m[0], 1, -1));
                    // <https://drafts.csswg.org/css-fonts#font-face-src-parsing>
                    if (false !== \strpos(',collection,embedded-opentype,opentype,svg,truetype,woff,woff2,', ',' . $test . ',')) {
                        $to .= $test;
                    } else if (false !== \strpos(',woff-variations,woff2-variations,truetype-variations,opentype-variations,', ',' . $test . ',')) {
                        $to .= \strtr($test, ['-' => ') tech(']);
                    } else {
                        $to .= $m[0];
                    }
                    continue;
                }
                if ('tech(' === \substr($to, -5)) {
                    $test = \trim(\substr($m[0], 1, -1));
                    // <https://drafts.csswg.org/css-fonts#font-face-src-parsing>
                    if (false !== \strpos(',color-COLRv0,color-COLRv1,color-SVG,color-sbix,color-CBDT,features-opentype,features-aat,features-graphite,incremental,palettes,variations,', ',' . $test . ',')) {
                        $to .= $test;
                    } else {
                        $to .= $m[0];
                    }
                    continue;
                }
                // <https://www.w3.org/TR/css-syntax-3#consume-a-url-token>
                // <https://www.w3.org/TR/css-syntax-3#url-token-diagram>
                if ('url(' === \substr($to, -4) && \strcspn(\substr($m[0], 1, -1), $c4 . '"\'()\\\\') === \strlen($m[0]) - 2) {
                    $to .= \substr($m[0], 1, -1);
                    continue;
                }
                $to .= $m[0];
                continue;
            }
            // `#…`
            if ('#' === $c && \preg_match('/^#([a-f\d]{1,2}){3,4}\b/i', $chop, $m)) {
                $from = \substr($from, \strlen($m[0]));
                $n = \strlen($v = \strtolower(\substr($m[0], 1)));
                if (4 === $n && 'f' === \substr($v, -1)) {
                    $v = \substr($v, 0, -1);
                    $n -= 1;
                } else if (8 === $n && 'ff' === \substr($v, -2)) {
                    $v = \substr($v, 0, -2);
                    $n -= 2;
                }
                if (6 === $n && $v[0] === $v[1] && $v[2] === $v[3] && $v[4] === $v[5]) {
                    $v = $v[0] . $v[2] . $v[4];
                } else if (8 === $n && $v[0] === $v[1] && $v[2] === $v[3] && $v[4] === $v[5] && $v[6] === $v[7]) {
                    $v = $v[0] . $v[2] . $v[4] . $v[6];
                }
                $to .= '#' . $v;
                continue;
            }
            // `/*…*/`
            if ('/' === $c && '*' === ($chop[1] ?? 0) && \preg_match('/^\/\*[^*]*\*+([^\/*][^*]*\*+)*\//', $chop, $m)) {
                $from = \substr($from, \strlen($m[0]));
                // `/*!…*/` or `/**…*/`
                if (false !== \strpos('!*', $m[0][2])) {
                    if (false !== \strpos($m[0], "\n")) {
                        $to .= '/*' . \substr($m[0], 3);
                    } else {
                        $to .= '/*' . \trim(\substr($m[0], 3, -2)) . '*/';
                    }
                // Case of `asdf/*asdf*/asdf{asdf:asdf}`
                } else if ("" !== $to && false === \strpos($c3, \substr($to, -1))) {
                    $to .= ' ';
                }
                continue;
            }
            // `[…]`
            if ('[' === $c && \preg_match('/^\[(?>' . $r3 . '|[^]]+)+\]/', $chop, $m)) {
                $from = \substr($from, \strlen($m[0]));
                if ("" !== $to && false !== \strpos($c4, \substr($to, -1))) {
                    $to = \trim($to) . ' ';
                }
                $to .= '[';
                foreach (\preg_split('/((?>' . $r3 . '|[$*=^|~]|\s+))/', \trim(\substr($m[0], 1, -1)), -1, \PREG_SPLIT_DELIM_CAPTURE | \PREG_SPLIT_NO_EMPTY) as $v) {
                    if ("" === ($v = \trim($v))) {
                        continue;
                    }
                    // <https://mothereff.in/unquoted-attributes>
                    if (false !== \strpos('"\'', $v[0]) && "" !== ($test = \substr($v, 1, -1))) {
                        if ('-' === $test || 0 === \strpos($test, '--') || \is_numeric($test[0]) || ('-' === $test[0] && \is_numeric($test[1]))) {
                            $to .= $v;
                            continue;
                        }
                        if (!\preg_match('/^[\w-]+$/', $test)) {
                            $to .= $v;
                            continue;
                        }
                        $to .= $test;
                        continue;
                    }
                    if (false === \strpos('"$\'*=[]^|~', \substr($to, -1)) && false === \strpos('"$\'*=[]^|~', $v[0])) {
                        $to .= ' '; // Case of `[asdf=asdf i]` or `[asdf="asdf"i]`
                    }
                    $to .= $v;
                }
                $to .= ']';
                continue;
            }
            $from = \substr($from, 1);
            if (false !== \strpos(')]', $c)) {
                $to = \trim($to, ',');
            } else if ('}' === $c) {
                $to = \trim($to, ';');
                if ('{' === \substr($to, -1)) {
                    $to = \trim(\preg_replace('/[^{}]+(?>' . $r3 . '|[^{}]+)*\{$/', "", $to)); // Drop empty selector(s)
                    continue;
                }
            }
            $to .= $c;
        }
        if ("" !== $from) {
            $to .= $from;
        }
        return "" !== ($to = \trim($to)) ? $to : null;
    }
}