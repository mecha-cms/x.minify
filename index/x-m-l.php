<?php

namespace x\minify {
    function x_m_l(?string $from): ?string {
        if ("" === ($from = \trim($from ?? ""))) {
            return null;
        }
        $c1 = '<&';
        $c2 = " \n\r\t";
        $r1 = '"[^"]*"';
        $r2 = "'[^']*'";
        $r3 = $r1 . '|' . $r2;
        $to = "";
        while (false !== ($chop = \strpbrk($from, $c1 . $c2))) {
            if ("" !== ($v = \strstr($from, $c = $chop[0], true))) {
                $from = $chop;
                $to .= $v;
            }
            // <https://www.w3.org/TR/xml#dt-stag>
            // `<…`
            if ('<' === $c && isset($chop[1]) && false === \strpos($c2, $chop[1])) {
                // <https://www.w3.org/TR/xml#d0e1149>
                // `<!--…`
                if (0 === \strpos($chop, '<!--') && false !== ($n = \strpos($chop, '-->'))) {
                    $from = \substr($from, \strlen(\substr($chop, 0, $n + 3)));
                    continue;
                }
                // <https://www.w3.org/TR/xml#d0e1271>
                // `<![CDATA[…`
                if (0 === \strpos($chop, '<![CDATA[') && false !== ($n = \strpos($chop, ']]>'))) {
                    $from = \substr($from, \strlen($v = \substr($chop, 0, $n + 3)));
                    $to .= $v;
                    continue;
                }
                if (\preg_match('/^<(?>' . $r3 . '|[^>])++>/', $chop, $m)) {
                    $from = \trim(\substr($from, \strlen($m[0])));
                    $to = \trim($to);
                    // <https://www.w3.org/TR/xml#dt-etag>
                    if ('/' === ($chop[1] ?? 0) && '>' === \substr($to, -1) && \preg_match('/<' . \substr(\strtok($m[0], $c2 . '>'), 2) . '(\s(?>' . $r3 . '|[^\/>])++)?>$/', $to)) {
                        // <https://www.w3.org/TR/xml#dt-empty>
                        $to = \substr($to, 0, -1) . '/>';
                        continue;
                    }
                    foreach (\preg_split('/(' . $r3 . '|[!\/<=>?]|\s+)/', $m[0], -1, \PREG_SPLIT_DELIM_CAPTURE | \PREG_SPLIT_NO_EMPTY) as $v) {
                        if (false !== \strpos('"\'', $v[0]) && false !== \strpos($v, '&')) {
                            $v = \preg_replace_callback('/&(?>#x[a-f\d]{1,6}|#\d{1,7}|[a-z][a-z\d]{1,31});/i', static function ($m) use ($v) {
                                $test = \html_entity_decode($m[0], \ENT_HTML5 | \ENT_QUOTES, 'UTF-8');
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
                    continue;
                }
            }
            // <https://www.w3.org/TR/xml#dt-charref>
            // <https://www.w3.org/TR/xml#dt-entref>
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
                $from = \substr($from, $n);
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