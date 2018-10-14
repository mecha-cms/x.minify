<?php namespace fn\minify;


/**
 * 0: Remove
 * 1: Keep
 * 2: Remove if/but/when …
 */

function pattern($pattern, $in) {
    return preg_split('#(' . implode('|', $pattern) . ')#', $in, null, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);
}

function css($in, $comment = 2, $quote = 2) {
    if (!is_string($in) || !$in = \n(trim($in))) return $in;
    $out = $prev = "";
    foreach (pattern([
        \Minify::COMMENT_CSS,
        \Minify::STRING
    ], $in) as $part) {
        if (trim($part) === "") continue;
        if ($comment !== 1 && strpos($part, '/*') === 0 && substr($part, -2) === '*/') {
            if (
                $comment === 2 && (
                    // Detect special comment(s) from the third character. It should be a `!` or `*` → `/*! keep */` or `/** keep */`
                    isset($part[2]) && strpos('*!', $part[2]) !== false ||
                    // Detect license comment(s) from the content. It should contains character(s) like `@license`
                    stripos($part, '@licence') !== false || // noun
                    stripos($part, '@license') !== false || // verb
                    stripos($part, '@preserve') !== false
                )
            ) {
                $out .= $part;
            }
            continue;
        }
        if ($part[0] === '"' && substr($part, -1) === '"' || $part[0] === "'" && substr($part, -1) === "'") {
            // Remove quote(s) where possible…
            $q = $part[0];
            // Make sure URL does no contains `[ \n\t"']` character(s)
            $clean = \t($part, $q); // Trim quote(s)
            $ok = strcspn($clean, " \n\t\"'") === strlen($clean);
            if (
                $quote !== 1 && (
                    // <https://www.w3.org/TR/CSS2/syndata.html#uri>
                    substr($prev, -4) === 'url(' && preg_match('#\burl\($#', $prev) && $ok ||
                    // <https://www.w3.org/TR/CSS2/syndata.html#characters>
                    substr($prev, -1) === '=' && preg_match('#^' . $q . '[a-zA-Z_][\w-]*?' . $q . '$#', $part)
                )
            ) {
                $part = $clean;
            }
            $out .= $part;
        } else {
            $out .= css_union($part);
        }
        $prev = $part;
    }
    return trim($out);
}

function css_union($in) {
    if (stripos($in, 'calc(') !== false) {
        // Keep important white–space(s) in `calc()`
        $in = preg_replace_callback('#\b(calc\()\s*(.*?)\s*\)#i', function($m) {
            return $m[1] . preg_replace('#\s+#', X, $m[2]) . ')';
        }, $in);
    }
    $in = preg_replace([
        // Fix case for `#foo<space>[bar="baz"]`, `#foo<space>*` and `#foo<space>:first-child` [^1]
        '#(?<=[\w])\s+(\*|\[|:[\w-]+)#',
        // Fix case for `[bar="baz"]<space>.foo`, `*<space>.foo`, `:nth-child(2)<space>.foo` and `@media<space>(foo: bar)<space>and<space>(baz: qux)` [^2]
        '#([*\]\)])\s+(?=[\w\#.])#', '#\b\s+\(#', '#\)\s+\b#',
        // Minify HEX color code … [^3]
        '#\#([a-f\d])\1([a-f\d])\2([a-f\d])\3\b#i',
        // Remove white–space(s) around punctuation(s) [^4]
        '#\s*([~!@*\(\)+=\{\}\[\]:;,>\/])\s*#',
        // Replace zero unit(s) with `0` [^5]
        // <https://www.w3.org/Style/Examples/007/units.en.html>
        '#\b(?<!\d\.)(?:0+\.)?0+(?:(?:cm|em|ex|in|mm|pc|pt|px|rem|vh|vmax|vmin|vw)\b)#',
        // Replace `0.6` with `.6` [^6]
        '#\b0+\.(\d+)#',
        // Replace `:0 0`, `:0 0 0` and `:0 0 0 0` with `:0` [^7]
        '#:(0\s+){0,3}0(?=[!,;\)\}]|$)#',
        // Replace `background(?:-position)?:(0|none)` with `background$1:0 0` [^8]
        '#\b(background(?:-position)?):(?:0|none)([;,\}])#i',
        // Replace `(border(?:-radius)?|outline):none` with `$1:0` [^9]
        '#\b(border(?:-radius)?|outline):none\b#i',
        // Remove empty selector(s) [^10]
        '#(^|[\{\}])(?:[^\{\}]+)\{\}#',
        // Remove the last semi–colon and replace multiple semi–colon(s) with a semi–colon [^11]
        '#;+([;\}])#',
        // Replace multiple white–space(s) with a space [^12]
        '#\s+#'
    ], [
        // [^1]
        X . '$1',
        // [^2]
        '$1' . X, X . '(', ')' . X,
        // [^3]
        '#$1$2$3',
        // [^4]
        '$1',
        // [^5]
        '0',
        // [^6]
        '.$1',
        // [^7]
        ':0',
        // [^8]
        '$1:0 0$2',
        // [^9]
        '$1:0',
        // [^10]
        '$1',
        // [^11]
        '$1',
        // [^12]
        ' '
    ], $in);
    return trim(str_replace(X, ' ', $in));
}

function html($in, $comment = 2, $quote = 1) {
    if (!is_string($in) || !$in = \n(trim($in))) return $in;
    $out = $prev = "";
    foreach (pattern([
        \Minify::COMMENT_HTML,
        \Minify::HTML_KEEP,
        \Minify::HTML,
        \Minify::HTML_ENT
    ], $in) as $part) {
        if ($part === "\n") continue;
        if ($part !== ' ' && trim($part) === "" || $comment !== 1 && strpos($part, '<!--') === 0) {
            // Detect IE conditional comment(s) by its closing tag …
            if ($comment === 2 && substr($part, -12) === '<![endif]-->') {
                $out .= $part;
            }
            continue;
        }
        if ($part[0] === '<' && substr($part, -1) === '>') {
            $out .= html_union($part, $quote);
        } else if ($part[0] === '&' && substr($part, -1) === ';' && $part !== '&lt;' && $part !== '&gt;' && $part !== '&amp;') {
            $out .= html_entity_decode($part); // Evaluate HTML entit(y|ies)
        } else {
            $out .= preg_replace('#\s+#', ' ', $part);
        }
        $prev = $part;
    }
    // Force space with `&#x0020;` and line–break with `&#x000A;`
    return str_ireplace(['&#x0020;', '&#x20;', '&#x000A;', '&#xA;'], [' ', ' ', N, N], trim($out));
}

function html_union($in, $quote) {
    global $url;
    if (
        strpos($in, ' ') === false &&
        strpos($in, "\n") === false &&
        strpos($in, "\t") === false
    ) return $in;
    return preg_replace_callback('#<\s*([^\/\s]+)\s*(?:>|(\s[^<>]+?)\s*>)#', function($m) use($quote, $url) {
        if (isset($m[2])) {
            // Minify inline CSS(s)
            if (stripos($m[2], ' style=') !== false) {
                $m[2] = preg_replace_callback('#( style=)([\'"]?)(.*?)\2#i', function($m) {
                    return $m[1] . $m[2] . css($m[3]) . $m[2];
                }, $m[2]);
            }
            // Minify URL(s)
            if (strpos($m[2], '://') !== false) {
                $host = $url->protocol . $url->host;
                $m[2] = str_replace([
                    $host . '/',
                    $host . '?',
                    $host . '&',
                    $host . '#',
                    $host . '"',
                    $host . "'"
                ], [
                    '/',
                    '?',
                    '&',
                    '#',
                    '/"',
                    "/'"
                ], $m[2]);
            }
            $a = 'a(sync|uto(focus|play))|c(hecked|ontrols)|d(efer|isabled)|hidden|ismap|loop|multiple|open|re(adonly|quired)|s((cop|elect)ed|pellcheck)';
            $a = '<' . $m[1] . preg_replace([
                // From `a="a"`, `a='a'`, `a="true"`, `a='true'`, `a=""` and `a=''` to `a` [^1]
                '#\s(' . $a . ')(?:=([\'"]?)(?:true|\1)?\2)#i',
                // Remove extra white–space(s) between HTML attribute(s) [^2]
                '#\s*([^\s=]+?)(=(?:\S+|([\'"]?).*?\3)|$)#',
                // From `<img />` to `<img/>` [^3]
                '#\s+\/$#'
            ], [
                // [^1]
                ' $1',
                // [^2]
                ' $1$2',
                // [^3]
                '/'
            ], str_replace("\n", ' ', $m[2])) . '>';
            return $quote !== 1 ? html_union_attr($a) : $a;
        }
        return '<' . $m[1] . '>';
    }, $in);
}

function html_union_attr($in) {
    if (strpos($in, '=') === false) return $in;
    return preg_replace_callback('#=(' . \Minify::STRING . ')#', function($m) {
        $q = $m[1][0];
        if (strpos($m[1], ' ') === false && preg_match('#^' . $q . '[a-zA-Z_][\w-]*?' . $q . '$#', $m[1])) {
            return '=' . \t($m[1], $q);
        }
        return $m[0];
    }, $in);
}

function js($in, $comment = 2, $quote = 2) {
    if (!is_string($in) || !$in = \n(trim($in))) return $in;
    $out = $prev = "";
    foreach (pattern([
        \Minify::COMMENT_CSS,
        \Minify::STRING,
        \Minify::COMMENT_JS,
        \Minify::PATTERN_JS
    ], $in) as $part) {
        if (trim($part) === "") continue;
        if ($comment !== 1 && (
            strpos($part, '//') === 0 || // Remove inline comment(s)
            strpos($part, '/*') === 0 && substr($part, -2) === '*/'
        )) {
            if (
                $comment === 2 && (
                    // Detect special comment(s) from the third character. It should be a `!` or `*` → `/*! keep */` or `/** keep */`
                    isset($part[2]) && strpos('*!', $part[2]) !== false ||
                    // Detect license comment(s) from the content. It should contains character(s) like `@license`
                    stripos($part, '@licence') !== false || // noun
                    stripos($part, '@license') !== false || // verb
                    stripos($part, '@preserve') !== false
                )
            ) {
                $out .= $part;
            }
            continue;
        }
        if ($part[0] === '/' && (substr($part, -1) === '/' || preg_match('#\/[gimuy]*$#', $part))) {
            $out .= $part;
        } else if (
            $part[0] === '"' && substr($part, -1) === '"' ||
            $part[0] === "'" && substr($part, -1) === "'" ||
            $part[0] === '`' && substr($part, -1) === '`' // ES6
        ) {
            // TODO: Remove quote(s) where possible …
            $out .= $part;
        } else {
            $out .= js_union($part);
        }
        $prev = $part;
    }
    return $out;
}

function js_union($in) {
    return trim(preg_replace([
        // Remove white–space(s) around punctuation(s) [^1]
        '#\s*([!%&*\(\)\-=+\[\]\{\}|;:,.<>?\/])\s*#',
        // Remove the last semi–colon and comma [^2]
        '#[;,]([\]\}\)])#',
        // Replace `true` with `!0` and `false` with `!1` [^3]
        '#\btrue\b#', '#\bfalse\b#', '#\b(return\s?)\s*\b#',
        // Replace `new Array(x)` with `[x]` … [^4]
        '#\b(?:new\s+)?Array\((.*?)\)#', '#\b(?:new\s+)?Object\((.*?)\)#'
    ], [
        // [^1]
        '$1',
        // [^2]
        '$1',
        // [^3]
        '!0', '!1', '$1',
        // [^4]
        '[$1]', '{$1}'
    ], $in));
}

// Set property by file extension
\Minify::_('css', __NAMESPACE__ . '\css');
\Minify::_('html', __NAMESPACE__ . '\html');
\Minify::_('js', __NAMESPACE__ . '\js');