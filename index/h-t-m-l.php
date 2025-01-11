<?php

namespace x\minify {
    function h_t_m_l(?string $from): ?string {
        if ("" === ($from = \trim($from ?? ""))) {
            return null;
        }
        $c1 = '<&';
        $c2 = " \n\r\t";
        $r1 = '"[^"]*"';
        $r2 = "'[^']*'";
        $r3 = $r1 . '|' . $r2;
        $to = "";
        $w1 = \function_exists(__NAMESPACE__ . "\\c_s_s");
        $w2 = \function_exists(__NAMESPACE__ . "\\j_s");
        $w3 = \function_exists(__NAMESPACE__ . "\\j_s_o_n");
        $w4 = \function_exists(__NAMESPACE__ . "\\x_m_l");
        // <https://html.spec.whatwg.org/multipage/common-microsyntaxes.html#boolean-attributes>
        $attr = [
            'allowfullscreen' => 1,
            'allowpaymentrequest' => 1,
            'async' => 1,
            'autofocus' => 1,
            'autoplay' => 1,
            'checked' => 1,
            'controls' => 1,
            'default' => 1,
            'defer' => 1,
            'disabled' => 1,
            'formnovalidate' => 1,
            'hidden' => 1,
            'ismap' => 1,
            'itemscope' => 1,
            'loop' => 1,
            'multiple' => 1,
            'muted' => 1,
            'nomodule' => 1,
            'novalidate' => 1,
            'open' => 1,
            'playsinline' => 1,
            'readonly' => 1,
            'required' => 1,
            'reversed' => 1,
            'selected' => 1,
            'truespeed' => 1
        ];
        while (false !== ($chop = \strpbrk($from, $c1 . $c2))) {
            if ("" !== ($v = \strstr($from, $c = $chop[0], true))) {
                $from = $chop;
                $to .= $v;
            }
            // <https://www.w3.org/TR/xml#dt-stag>
            // `<…`
            if ('<' === $c && isset($chop[1]) && false === \strpos($c2, $chop[1])) {
                // <https://html.spec.whatwg.org/multipage/syntax.html#comments>
                // `<!--…`
                if (0 === \strpos($chop, '<!--') && false !== ($n = \strpos($chop, '-->'))) {
                    $from = \substr($from, \strlen($v = \substr($chop, 0, $n + 3)));
                    // <https://en.wikipedia.org/wiki/Conditional_comment>
                    if ('<![endif]-->' === \substr($v, -12)) {
                        $to .= \substr($v, 0, $n = \strpos($v, '>') + 1) . \x\minify\h_t_m_l(\substr($v, $n, -12)) . \substr($v, -12);
                        continue;
                    }
                    if (' ' === \substr($to, -1) && '-->') {
                        $from = \ltrim($from);
                    }
                    continue;
                }
                // <https://html.spec.whatwg.org/multipage/syntax.html#cdata-sections>
                // `<![CDATA[…`
                if (0 === \strpos($chop, '<![CDATA[') && false !== ($n = \strpos($chop, ']]>'))) {
                    $from = \substr($from, \strlen($v = \substr($chop, 0, $n + 3)));
                    $to .= $v;
                    continue;
                }
                if (\preg_match('/^<(?>' . $r3 . '|[^>])++>/', $chop, $m)) {
                    $from = \substr($from, \strlen($m[0]));
                    $q = \substr(\strtok($m[0], $c2 . '>'), 1);
                    foreach (\preg_split('/(' . $r3 . '|[!\/<=>?]|\s+)/', $m[0], -1, \PREG_SPLIT_DELIM_CAPTURE | \PREG_SPLIT_NO_EMPTY) as $v) {
                        $f = \ENT_HTML5 | \ENT_QUOTES;
                        if ('=' === \substr($to, -1) && ($test = \strrchr($to, ' '))) {
                            $test = \substr($test, 1, -1);
                            if ('class' === $test && false !== \strpos('"\'', $v[0])) {
                                $v = $v[0] . \trim(\implode(' ', \array_unique(\preg_split('/\s+/', \trim(\substr($v, 1, -1)))))) . $v[0];
                            } else if ('style' === $test && $w1) {
                                if (false !== \strpos('"\'', $v[0])) {
                                    $v = $v[0] . \rtrim(\htmlspecialchars(\substr(\x\minify\c_s_s('x{' . \htmlspecialchars_decode(\substr($v, 1, -1), $f) . '}'), 2, -1), $f), ';') . $v[0];
                                } else {
                                    $v = \rtrim(\htmlspecialchars(\substr(\x\minify\c_s_s('x{' . \htmlspecialchars_decode(\substr($v, 1, -1), $f) . '}'), 2, -1), $f), ';');
                                }
                            } else if (0 === \strpos($test, 'on') && \preg_match('/^on\S+$/', $test) && $w2) {
                                if (false !== \strpos('"\'', $v[0])) {
                                    $v = $v[0] . \rtrim(\htmlspecialchars(\x\minify\j_s(\htmlspecialchars_decode(\substr($v, 1, -1), $f)), $f), ';') . $v[0];
                                } else {
                                    $v = \rtrim(\htmlspecialchars(\x\minify\j_s(\htmlspecialchars_decode($v, $f)), $f), ';');
                                }
                            } else if (isset($attr[$test]) && ("''" === $v || '""' === $v || "'" . $test . "'" === $v || '"' . $test . '"' === $v || $test === $v || false !== \strpos($c2 . '/>', $v))) {
                                $to = \substr($to, 0, -1);
                                continue;
                            }
                        }
                        if (false !== \strpos('"\'', $v[0]) && false !== \strpos($v, '&')) {
                            // <https://html.spec.whatwg.org/multipage/syntax.html#character-references>
                            $v = \preg_replace_callback('/&(?>#x[a-f\d]{1,6}|#\d{1,7}|[a-z][a-z\d]{1,31});/i', static function ($m) use ($f, $v) {
                                $test = \html_entity_decode($m[0], $f, 'UTF-8');
                                if (false !== \strpos('&<>' . $v[0], $test)) {
                                    return $m[0];
                                }
                                return $test;
                            }, $v);
                        }
                        if (false !== \strpos('/>?', $v)) {
                            $to = \rtrim($to) . $v;
                            continue;
                        }
                        $to .= "" === ($v = \trim($v)) ? ' ' : $v;
                    }
                    if (false === \strpos('/?', \substr($to, -2, 1))) {
                        if (false !== \strpos(',pre,script,style,svg,textarea,', ',' . $q . ',') && false !== ($n = \strpos($from, '</' . $q . '>'))) {
                            $content = \substr($from, 0, $n);
                            // <https://html.spec.whatwg.org/multipage/scripting.html#attr-script-type>
                            if ('script' === $q) {
                                $content = \trim($content);
                                if ('<![CDATA[' === \substr($content, 0, 9) && ']]>' === \substr($content, -3)) {
                                    $content = \substr($content, 9, -3); // Remove character data section
                                }
                                if (false !== \strpos($m[0], 'type=')) {
                                    // <https://html.spec.whatwg.org/multipage/webappapis.html#import-maps>
                                    // <https://www.w3.org/TR/json-ld1>
                                    if ($w3 && \preg_match('/\stype=(["\']?)(?>application\/ld\+json|importmap)\1/', $m[0])) {
                                        $content = \x\minify\j_s_o_n($content);
                                    } else if ($w2) {
                                        $content = \x\minify\j_s($content);
                                    }
                                } else if ($w2) {
                                    $content = \x\minify\j_s($content);
                                }
                                $content = \x\minify\j_s($content);
                            } else if ('style' === $q && $w1) {
                                $content = \trim($content);
                                if ('<![CDATA[' === \substr($content, 0, 9) && ']]>' === \substr($content, -3)) {
                                    $content = \substr($content, 9, -3); // Remove character data section
                                }
                                $content = \x\minify\c_s_s($content);
                            } else if ('svg' === $q && $w4) {
                                $content = \x\minify\x_m_l($content);
                            }
                            $to .= $content;
                            $from = \substr($from, $n);
                        }
                    }
                    continue;
                }
            }
            // <https://html.spec.whatwg.org/multipage/syntax.html#character-references>
            if ('&' === $c && \strpos($chop, ';') > 1 && \preg_match('/^&(?>#x[a-f\d]{1,6}|#\d{1,7}|[a-z][a-z\d]{1,31});/i', $chop, $m)) {
                $from = \substr($from, \strlen($m[0]));
                $v = \html_entity_decode($m[0], \ENT_HTML5 | \ENT_QUOTES, 'UTF-8');
                if (false !== \strpos('&<>', $v)) {
                    $to .= $m[0];
                    continue;
                }
                $to .= $v;
                continue;
            }
            if ($n = \strspn($chop, $c2)) {
                $w = \substr($from, 0, $n);
                $from = \substr($from, $n);
                if ('>' === \substr($to, -1) && ($v = \strrchr($to, '<'))) {
                    // Previous is comment or character data section
                    if ('-->' === \substr($v, -3) || ']]>' === \substr($v, -3)) {
                        // Next is close tag
                        if ('</' === \substr($from, 0, 2)) {
                            continue;
                        }
                        if (false === \strpos($w, "\n")) {
                            $to .= ' ';
                        }
                        continue;
                    }
                    // Previous is close tag
                    if ('/' === ($v[1] ?? 0) && false === \strpos($v, '"') && false === \strpos($v, "'")) {
                        if (' ' === $w) {
                            $to .= '</' === \substr($from, 0, 2) ? "" : $w;
                        }
                        continue;
                    }
                    // Previous is void tag
                    if ('/' === \substr($v, -2, 1)) {
                        // Previous is `<br/>`, `<hr/>`, or `<wbr/>` tag
                        if (false !== \strpos(',br,hr,wbr,', ',' . \substr(\strtok($v, $c2 . '>/'), 1) . ',')) {
                            continue;
                        }
                        if (' ' === $w) {
                            $to .= '</' === \substr($from, 0, 2) ? "" : $w;
                        }
                        continue;
                    }
                    // Previous is open tag, next is close tag of the open tag
                    if (' ' === $w && \substr(\strtok($v, $c2 . '>'), 1) === \substr(\strtok($from, $c2 . '>'), 2)) {
                        $to .= $w;
                    }
                    // Previous is `<img>`, or `<input>` tag
                    if (' ' === $w && false !== \strpos(',img,input,', ',' . \substr(\strtok($v, $c2 . '>'), 1) . ',')) {
                        $to .= '</' === \substr($from, 0, 2) ? "" : $w;
                    }
                    // Previous is open tag
                    continue;
                }
                if ('<' === ($from[0] ?? 0) && \strpos($from, '>') > 1) {
                    // Next is close tag
                    if ('/' === ($from[1] ?? 0)) {
                        continue;
                    }
                    // Next is open tag
                    if (' ' !== $w) {
                        // Next is comment or character data section
                        if ('<!--' === \substr($from, 0, 4) || '<![CDATA[' === \substr($from, 0, 9)) {
                            $to .= ' ';
                            continue;
                        }
                        continue;
                    }
                    // Next is `<br/>`, `<hr/>`, or `<wbr/>` tag
                    if (false !== \strpos(',br,hr,wbr,', ',' . \substr(\strtok($from, $c2 . '>/'), 1) . ',')) {
                        continue;
                    }
                }
                $to .= ' ';
                continue;
            }
            $from = \substr($from, 1);
            $to .= $c;
        }
        if ("" !== $from) {
            $to .= $from;
        }
        return "" !== ($to = \trim($to)) ? $to : null;
    }
}