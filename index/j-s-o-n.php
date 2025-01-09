<?php

namespace x\minify {
    function j_s_o_n(?string $from): ?string {
        if ("" === ($from = \trim($from ?? ""))) {
            return null;
        }
        if ('""' === $from || '[]' === $from || 'false' === $from || 'null' === $from || 'true' === $from || '{}' === $from || \is_numeric($from)) {
            return $from;
        }
        $c1 = ',:[]{}';
        $to = "";
        while (false !== ($chop = \strpbrk($from, '"' . $c1))) {
            if ("" !== ($v = \strstr($from, $c = $chop[0], true))) {
                $from = $chop;
                $to .= \trim($v);
            }
            if (false !== \strpos($c1, $c)) {
                $from = \substr($from, 1);
                $to .= $chop[0];
                continue;
            }
            if ('""' === \substr($chop, 0, 2)) {
                $from = \substr($from, 2);
                $to .= '""';
                continue;
            }
            if ('"' === $c && \preg_match('/^"[^"\\\\]*(?>\\\\.[^"\\\\]*)*"/', $chop, $m)) {
                $from = \substr($from, \strlen($m[0]));
                $to .= $m[0];
                continue;
            }
            $from = "";
            $to .= \trim($chop); // `false`, `null`, `true`, `1`, `1.0`
        }
        if ("" !== $from) {
            $to .= \trim($from);
        }
        return "" !== ($to = \trim($to)) ? $to : null;
    }
}