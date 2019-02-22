<?php

$NUMBER = '(?:-?(?:(?:\d+)?\.)?\d+)';
$STRING = function(string $limit = '\'"', string $not = ""): string {
    $out = [];
    foreach (str_split($limit) as $v) {
        $out[] = $v . '(?:[^' . $v . $not . '\\\]|\\\.)*' . $v;
    }
    return '(?:' . implode('|', $out) . ')';
};

// List of common token(s) in code
$TOKEN = '(?:[!%&*\(\)\-=+\[\]\{\}|;:,.<>?\/])';
// Define root URL to be removed
$URL = $GLOBALS['URL']['scheme'] . '://' . $GLOBALS['URL']['host'];

// Generate XML tag pattern
$XML = function(string $tag, string $end = '>', $wrap = true): string {
    // <https://www.w3.org/TR/html5/syntax.html#elements-attributes>
    $tag = '(?:' . $tag . ')';
    return '(?:<' . $tag . '(?:\s[^>]*)?' . $end . ($wrap ? '[\s\S]*?</' . $tag . '>' : "") . ')';
};

$css = function(string $in, int $comment = 2, int $quote = 2) use(
    &$NUMBER,
    &$STRING,
    &$TOKEN,
    &$URL,
    &$css_unit
): string {
    if (($in = trim($in)) === "") {
        return "";
    }
    // Preserve single white-space around `*` selector to
    // prevent `* span` being minified into `*span`
    $ANY = '(?:\s?[*]\s?)';
    // <https://www.w3.org/TR/CSS2/syndata.html#value-def-identifier>
    $KEY = '[a-z_-][a-z\d_-]*';
    // Preserve single white-space around `[foo="bar"]` selector to
    // prevent `span [title]` being minified into `span[title]`
    $ATTR = '(?:\s?\[' . $KEY . '(?:=.*?)?\]\s?)';
    // Match character(s) starts from `calc(` to `)`
    $CALC = '(?:\s?\bcalc\([^}:;,]+\))';
    // Match CSS comment
    $COMMENT = '(?:/\*[\s\S]*?\*/)';
    // Match HEX color code
    $HEX = '(?:\#(?:[a-f\d]{3}){1,2}\b)';
    // Match pseudo selector, preserve single white-space around `:foo` to
    // prevent `article :focus` being minified into `article:focus`
    $PSEUDO = '(?:\s?:[a-z-][a-z\d-]*\b)';
    // Match CSS string
    $S = $STRING('\'"', '\n');
    // Match `src()` and `url()` function to minify the URL in it where possible
    $U = '(?:\s?\b(?:src|url)\(' . $S . '\))';
    // Match character(s) from `var(` to `)`
    $VAR = '(?:\s?\bvar\([^}:;,]+\)\s?)';
    $out = "";
    $t = false;
    foreach (preg_split('#(' .
        // Prioritize string over comment because string cannot contains `\n` character
        $S . '|' .
        $COMMENT . '|' .
        $ANY . '|' .
        $ATTR . '|' .
        // Prioritize CSS function over pseudo selector to prevent `:calc`, `:var` match
        $CALC . '|' .
        $VAR . '|' .
        $U . '|' .
        $PSEUDO . '|' .
        $HEX . '|' .
        // Exclude `%` and `-` from token list
        str_replace(['%', '\-'], "", $TOKEN) .
    ')#i', n($in), null, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE) as $tok) {
        if (strpos($tok, '/*') === 0 && substr($tok, -2) === '*/') {
            if (
                $comment === 1 || (
                    $comment === 2 && (
                        // Detect special comment(s) from the third character
                        // It should be a `!` or `*` → `/*! keep */` or `/** keep */`
                        strpos('!*', $tok[2]) !== false ||
                        // Detect license comment(s) from the content
                        // It should contains character(s) like `@license`
                        strpos($tok, '@licence') !== false || // noun
                        strpos($tok, '@license') !== false || // verb
                        strpos($tok, '@preserve') !== false
                    )
                )
            ) {
                $out .= $tok;
                continue;
            }
            continue; // Is a comment, skip!
        }
        $v = trim($tok);
        // It is possible to right-trim the result if last character is a `}`, `:` or `,`
        $t = $out === "" || false !== strpos('}:;,', substr($out, -1));
        // White-space only, skip!
        if ($v === "") {
            // Do nothing!
        } else if ($v === '*') {
            $out .= $tok;
        // String block, don’t touch!
        } else if (
            strpos($v, '"') === 0 && substr($v, -1) === '"' ||
            strpos($v, "'") === 0 && substr($v, -1) === "'"
        ) {
            // Remove quote(s) where possible
            if ($quote === 2 && !is_numeric($v[1]) && preg_match('#^([\'"])(' . $KEY . ')\1$#i', $v, $m)) {
                $v = $m[2];
            }
            $out .= $v;
        // Maybe an attribute selector(s)
        } else if ($v[0] === '[' && substr($v, -1) === ']') {
            $out .= preg_replace_callback('#=(' . $S . ')\s*([is])?#', function($m) use($KEY, $quote) {
                // Remove quote(s) where possible
                $v = $m[1];
                $v = $quote === 2 && !is_numeric($v[1]) && preg_match('#^([\'"])(' . $KEY . ')\1$#i', $v, $w) ? $w[2] : preg_replace('#([\'"])(.*?)\1#', '"$2"', $v);
                // Must return `[foo="bar"i]` or `[foo=bar i]`
                return trim('=' . $v . ($v[0] === '"' || $v[0] === "'" ? "" : ' ') . ($m[2] ?? ""));
            }, $tok);
        // Maybe a variable
        } else if (strpos($v, 'var(') === 0) {
            $out .= preg_replace('#\s*,\s*#', ',', $tok);
        // Maybe a calculation
        } else if (strpos($v, 'calc(') === 0) {
            $tok = preg_replace('#\s+#', ' ', $tok);
            $out .= $t ? ltrim($tok) : $tok;
        // Maybe a URL function
        } else if (strpos($v, 'src(') === 0 || strpos($v, 'url(') === 0) {
            $tok = preg_replace_callback('#(' . $S . ')#', function($m) use($URL, $quote) {
                $v = substr(substr($m[1], 1), 0, -1);
                $v = str_replace($URL, "", $v); // Minify URL
                // Remove quote(s) where possible
                return $quote === 2 && strtr($v, '"\'>) ', '-----') === $v ? $v : '"' . $v . '"';
            }, $tok);
            $out .= $t ? ltrim($tok) : $tok;
        // Maybe a HEX color code
        } else if (strpos($v, '#') === 0 && (strlen($v) === 4 || strlen($v) === 7) && ctype_xdigit(substr($v, 1))) {
            // Minify HEX color code
            $out .= preg_replace('#([a-f\d])\1([a-f\d])\2([a-f\d])\3#i', '$1$2$3', strtolower($v));
        // Maybe a pseudo selector or a value with its `:` prefix
        } else if ($v[0] === ':') {
            $out .= $t ? ltrim($tok) : $tok;
        } else {
            // Remove last `,` if token is `)`
            if ($tok === ')') {
                $out = rtrim($out, ',') . $tok;
            // Remove last white-space if token is `{`
            } else if ($tok === '{') {
                $out = rtrim($out) . $tok;
            // Remove last `;` if token is `}`
            } else if ($tok === '}') {
                $out = rtrim($out, ';') . $tok;
                // Remove empty selector
                if (substr($out, -2) === '{}') {
                    $out = explode('}', $out);
                    array_pop($out);
                    array_pop($out);
                    $out = implode('}', $out);
                }
            } else {
                $out .= trim($tok);
            }
            $out = $css_unit($out);
        }
    }
    // Other(s)
    return preg_replace([
        '#\bbackground:(?:none|0)\b#',
        '#\b(border(?:-radius)?|outline):none\b#'
    ], [
        'background:0 0',
        '$1:0'
    ], $out);
};

$css_unit = function(string $in) use(&$NUMBER): string {
    $out = preg_replace('#\s+#', ' ', $in);
    // Minify unit(s)
    $out = preg_replace_callback('#(' . $NUMBER . ')(%|Hz|ch|cm|deg|dpcm|dpi|dppx|em|ex|grad|in|kHz|mm|ms|pc|pt|px|rad|rem|s|turn|vh|vmax|vmin|vw)\b#', function($m) {
        $v = $m[1];
        $v = strpos($v, '-') === 0 ? '-' . ltrim(substr($v, 1), '0') : ltrim($v, '0');
        $v = strpos($v, '.') === 0 ? trim($v, '0') : $v;
        $v = $v === '-' || $v === "" ? '0' : $v;
        return $v . ($v === '0' ? "" : $m[2]);
    }, $out);
    return preg_replace([
        '#\s+#',
        '#:0(?: 0){1,3}([};,])#'
    ], [
        ' ',
        ':0$1'
    ], $out);
};

$html = function(string $in, int $comment = 2, int $quote = 1) use(
    &$XML,
    &$css,
    &$html_content,
    &$html_data,
    &$js,
    &$json
): string {
    if (($in = trim($in)) === "") {
        return "";
    }
    // Match HTML comment
    $COMMENT = '<!\-{2}[\s\S]*?\-{2}>';
    // Match HTML entity reference
    $ENT = '&(?:[a-z\d]+|\#\d+|\#x[a-f\d]+);';
    // Don’t touch HTML content of `<pre>`, `<code>`, `<script>`, `<style>`, `<textarea>` element
    $KEEP = '(?:' . $XML('pre') .
        '|' . $XML('code') .
        '|' . $XML('kbd') .
        '|' . $XML('script') .
        '|' . $XML('style') .
        '|' . $XML('textarea') . ')';
    $out = "";
    foreach (preg_split('#(' .
        $COMMENT . '|' .
        $KEEP . '|' .
        // Match any HTML tag(s) include `<!DOCTYPE foo>` and `</foo>`
        $XML('[!/]?[\w:-]+', '>', false) . '|' .
        $ENT .
    ')#i', n($in), null, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE) as $tok) {
        $v = trim($tok);
        if ($tok === ' ') {
            // Fix case for `<button>Foo</button> <button>Bar</button>`
            // and `Foo bar baz <img src="smiley.gif"> qux`
            $out .= $tok; // Keep single white-space
            continue;
        } else if ($v === "") {
            continue; // Skip multiple white-space(s) and single line-break
        // May be a HTML tag
        } else if ($v[0] === '<' && substr($v, -1) === '>') {
            // This must be a HTML comment
            if (strpos($v, '<!--') === 0 && substr($v, -3) === '-->') {
                if ($comment === 1 || ($comment === 2 && substr($v, -12) === '<![endif]-->')) {
                    $out .= $v; // Keep legacy IE conditional comment(s)
                    continue;
                }
                continue; // Is a HTML comment, skip!
            }
            if (substr($v, -6) === '</pre>') {
                $tok = $html_content($tok, 'pre');
            // Minify embedded JS code
            } else if (substr($v, -9) === '</script>') {
                $fn = strpos($v, ' type=') !== false && preg_match('# type=([\'"])(application/json)\1[ >]#', $v) ? $json : $js;
                $tok = $html_content($tok, 'script', $fn);
            // Minify embedded CSS code
            } else if (substr($v, -8) === '</style>') {
                $tok = $html_content($tok, 'style', $css);
            }
            $out .= $html_data($tok, $quote); // Is a HTML tag
        } else if ($v[0] === '&' && substr($v, -1) === ';' && $v !== '&lt;' && $v !== '&gt;' && $v !== '&amp;') {
            $out .= html_entity_decode($v); // Evaluate HTML entity
        } else {
            $out .= $tok; // Other(s)
        }
    }
    return $out;
};

$html_content = function(string $in, string $t, $fn = false): string {
    return preg_replace_callback('#<' . $t . '(\s[^>]*)?>\s*([\s\S]*?)\s*</' . $t . '>#', function($m) use($fn, $t) {
        return '<' . $t . $m[1] . '>' . ($fn ? $fn($m[2]) : $m[2]) . '</' . $t . '>';
    }, $in);
};

$html_data = function(string $in, int $quote) use(
    &$KEY,
    &$URL,
    &$css
): string {
    return (
        strpos($in, ' ') === false &&
        strpos($in, "\n") === false &&
        strpos($in, "\t") === false
    ) ? $in : preg_replace_callback('#<([^>/\s]+)\s*(\s[^>]+?)?\s*>#', function($m) use(
        $URL,
        $css,
        $quote
    ) {
        $KEY = '[a-z_-][a-z\d_-]*';
        if (isset($m[2])) {
            // Minify CSS inline code
            if (strpos($m[2], ' style=') !== false) {
                $m[2] = preg_replace_callback('#( style=)([\'"]?)(.*?)\2#', function($m) use($css) {
                    return $m[1] . $m[2] . $css($m[3]) . $m[2];
                }, $m[2]);
            }
            // Minify URL in attribute value
            if (strpos($m[2], '=') !== false && strpos($m[2], '://') !== false) {
                $m[2] = str_replace([
                    '="' . $URL . '"',
                    "='" . $URL . "'",
                    '="' . $URL,
                    "='" . $URL
                ], [
                    '="/"',
                    "='/'",
                    '="',
                    "='"
                ], $m[2]);
            }
            $out = '<' . $m[1] . preg_replace([
                // From `a="a"`, `a='a'`, `a="true"`, `a='true'`, `a=""` and `a=''` to `a` [^1]
                '#\s(a(sync|uto(focus|play))|c(hecked|ontrols)|d(efer|isabled)|hidden|ismap|loop|multiple|open|re(adonly|quired)|s((cop|elect)ed|pellcheck))(?:=([\'"]?)(?:true|\1)?\2)#',
                // Remove extra white–space(s) between HTML attribute(s) [^2]
                '#\s*([^\s=]+)(=(?:\S+|([\'"]?).*?\3)|$)#',
                // From `<foo />` to `<foo/>` [^3]
                '#\s+\/$#'
            ], [
                // [^1]
                ' $1',
                // [^2]
                ' $1$2',
                // [^3]
                '/'
            ], strtr($m[2], "\n", ' ')) . '>';
            return $quote === 2 ? preg_replace('#=([\'"])(' . $KEY . ')\1#i', '=$2', $out) : $out;
        }
        return '<' . $m[1] . '>';
    }, $in);
};

$js = function(string $in, int $comment = 2, int $quote = 2) use(
    &$STRING,
    &$NUMBER,
    &$TOKEN
): string {
    if (($in = trim($in)) === "") {
        return "";
    }
    // Match JS comment inline
    $COMMENT = '//[^\n]*';
    // Match JS comment block
    $COMMENT_DOC = '/\*[\s\S]*?\*/';
    $KEY = '[a-z$_][a-z\d$_]*';
    $LITERAL = '\b(?:true|false)\b';
    $REGEX = '(?:' . $STRING('/', '\n') . '[gimuy]*[;,.\s])';
    $K = ['true' => '!0', 'false' => '!1'];
    $out = "";
    foreach (preg_split('#\s*(' .
        // Match `'foo bar'`, `"foo bar"`
        $STRING('\'"', '\n') . '|' .
        $COMMENT_DOC . '|' .
        // Match ``foo bar``
        $STRING('`') . '|' .
        $COMMENT . '|' .
        $REGEX . '|' .
        '\b' . $NUMBER . '\b|' .
        $LITERAL . '|' .
        $TOKEN .
    ')\s*#', n($in), null, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE) as $tok) {
        if (strpos($tok, '//') === 0) {
            continue; // Is a comment, skip!
        }
        if (strpos($tok, '/*') === 0 && substr($tok, -2) === '*/') {
            if (
                $comment === 1 || (
                    $comment === 2 && (
                        // Detect special comment(s) from the third character
                        // It should be a `!` or `*` → `/*! keep */` or `/** keep */`
                        strpos('!*', $tok[2]) !== false ||
                        // Detect license comment(s) from the content
                        // It should contains character(s) like `@license`
                        strpos($tok, '@licence') !== false || // noun
                        strpos($tok, '@license') !== false || // verb
                        strpos($tok, '@preserve') !== false
                    )
                )
            ) {
                $out .= $tok;
                continue;
            }
            continue; // Is a comment, skip!
        // String block, don’t touch!
        } else if (
            strpos($tok, '"') === 0 && substr($tok, -1) === '"' ||
            strpos($tok, "'") === 0 && substr($tok, -1) === "'" ||
            strpos($tok, "`") === 0 && substr($tok, -1) === "`"
        ) {
            $out .= $tok;
        // Maybe a pattern
        } else if ($tok[0] === '/' && preg_match('#^/.+/[a-z]*[;,.\s]$#', $tok)) {
            $out .= trim($tok);
        } else if (is_numeric($tok)) {
            $tok = strpos($tok, '-') === 0 ? '-' . ltrim(substr($tok, 1), '0') : ltrim($tok, '0');
            $tok = strpos($tok, '.') === 0 ? trim($tok, '0') : $tok;
            $out .= $tok === "" ? '0' : $tok; // Minify number(s)
        } else {
            if ($tok === ']') {
                $out = rtrim($out, ',') . $tok; // Remove the last comma
            } else if ($tok === '}') {
                $out = rtrim($out, ';,') . $tok; // Remove the last semi-colon and comma
            } else {
                $out .= $K[$tok] ?? $tok;
            }
        }
    }
    return $quote !== 1 ? preg_replace([
        // Minify object property [^1]
        '#([{,])([\'"])(' . $KEY . ')\2:#',
        // Minify object access [^2]
        '#(' . $KEY . '|\])\[([\'"])(' . $KEY . ')\2\]#'
    ], [
        // [^1]
        '$1$3:',
        // [^2]
        '$1.$3'
    ], $out) : $out;
};

$json = function(string $in): string {
    return json_encode(json_decode($in));
};

// Based on <https://php.net/manual/en/function.php-strip-whitespace.php#82437>
$php = function(string $in): string {
    $out = "";
    // White-space(s) around these token(s) can be ignored
    static $t = [
        T_AND_EQUAL => 1,                // &=
        T_BOOLEAN_AND => 1,              // &&
        T_BOOLEAN_OR => 1,               // ||
        T_COALESCE => 1,                 // ??
        T_CONCAT_EQUAL => 1,             // .=
        T_DEC => 1,                      // --
        T_DIV_EQUAL => 1,                // /=
        T_DOLLAR_OPEN_CURLY_BRACES => 1, // ${
        T_DOUBLE_ARROW => 1,             // =>
        T_DOUBLE_COLON => 1,             // ::
        T_INC => 1,                      // ++
        T_IS_EQUAL => 1,                 // ==
        T_IS_GREATER_OR_EQUAL => 1,      // >=
        T_IS_IDENTICAL => 1,             // ===
        T_IS_NOT_EQUAL => 1,             // != or <>
        T_IS_NOT_IDENTICAL => 1,         // !==
        T_IS_SMALLER_OR_EQUAL => 1,      // <=
        T_MINUS_EQUAL => 1,              // -=
        T_MOD_EQUAL => 1,                // %=
        T_MUL_EQUAL => 1,                // *=
        T_OBJECT_OPERATOR => 1,          // ->
        T_OR_EQUAL => 1,                 // |=
        T_PAAMAYIM_NEKUDOTAYIM => 1,     // ::
        T_PLUS_EQUAL => 1,               // +=
        T_POW => 1,                      // **
        T_POW_EQUAL => 1,                // **=
        T_SL => 1,                       // <<
        T_SL_EQUAL => 1,                 // <<=
        T_SPACESHIP => 1,                // <=>
        T_SR => 1,                       // >>
        T_SR_EQUAL => 1,                 // >>=
        T_XOR_EQUAL => 1                 // ^=
    ];
    $c = count($toks = token_get_all($in));
    $doc = $minify = false;
    $begin = $end = null;
    for ($i = 0; $i < $c; ++$i) {
        $tok = $toks[$i];
        if (is_array($tok)) {
            $id = $tok[0];
            $value = $tok[1];
            if ($id === T_INLINE_HTML) {
                $out .= $value;
                $minify = false;
            } else {
                if ($id === T_OPEN_TAG) {
                    if (strpos($value, ' ') !== false || strpos($value, "\n") !== false || strpos($value, "\t") !== false || strpos($value, "\r") !== false) {
                        $value = rtrim($value);
                    }
                    $out .= $value . ' ';
                    $begin = T_OPEN_TAG;
                    $minify = true;
                } else if ($id === T_OPEN_TAG_WITH_ECHO) {
                    $out .= $value;
                    $begin = T_OPEN_TAG_WITH_ECHO;
                    $minify = true;
                } else if ($id === T_CLOSE_TAG) {
                    if ($begin == T_OPEN_TAG_WITH_ECHO) {
                        $out = rtrim($out, '; ');
                    } else {
                        $value = ' ' . $value;
                    }
                    $out .= $value;
                    $begin = null;
                    $minify = false;
                } else if (isset($t[$id])) {
                    $out .= $value;
                    $minify = true;
                } else if ($id === T_ENCAPSED_AND_WHITESPACE || $id === T_CONSTANT_ENCAPSED_STRING) {
                    if ($value[0] === '"') {
                        $value = addcslashes($value, "\n\r\t");
                    }
                    $out .= $value;
                    $minify = true;
                } else if ($id === T_WHITESPACE) {
                    $n = $toks[$i + 1] ?? null;
                    if(!$minify && (!is_string($n) || $n === '$') && !isset($t[$n[0]])) {
                        $out .= ' ';
                    }
                    $minify = false;
                } else if ($id === T_START_HEREDOC) {
                    $out .= "<<<S\n";
                    $minify = false;
                    $doc = true; // Enter HEREDOC
                } elseif($id === T_END_HEREDOC) {
                    $out .= 'S;';
                    $minify = true;
                    $doc = false; // Exit HEREDOC
                    for ($j = $i + 1; $j < $c; ++$j) {
                        if (is_string($toks[$j]) && $toks[$j] === ';') {
                            $i = $j;
                            break;
                        } else if ($toks[$j][0] === T_CLOSE_TAG) {
                            break;
                        }
                    }
                } else if ($id === T_COMMENT || $id === T_DOC_COMMENT) {
                    $minify = true;
                } else {
                    $out .= $value;
                    $minify = false;
                }
            }
            $end = "";
        } else {
            if (strpos(';:', $tok) === false || $end !== $tok) {
                $out .= $tok;
                $end = $tok;
            }
            $minify = true;
        }
    }
    return $out;
};

$state = Extend::state('minify');
array_unshift($state['.css'], "");
array_unshift($state['.html'], "");
array_unshift($state['.js'], "");
array_unshift($state['.json'], "");
array_unshift($state['.php'], "");
Minify::_('CSS', [$css, $state['.css']]);
Minify::_('HTML', [$html, $state['.html']]);
Minify::_('JS', [$js, $state['.js']]);
Minify::_('JSON', [$json, $state['.json']]);
Minify::_('PHP', [$php, $state['.php']]);

// Alias(es)
Minify::_('css', Minify::_('CSS'));
Minify::_('html', Minify::_('HTML'));
Minify::_('js', Minify::_('JS'));
Minify::_('json', Minify::_('JSON'));
Minify::_('php', Minify::_('PHP'));