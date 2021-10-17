<?php

$do_minify_embed_css = true;
$do_minify_embed_js = true;

$NUMBER = '(?:-?(?:(?:\d+)?\.)?\d+)';
$STRING = static function(string $limit = '\'"', string $not = ""): string {
    $out = [];
    foreach (str_split($limit) as $v) {
        $out[] = $v . '(?:[^' . $v . $not . '\\\]|\\\.)*' . $v;
    }
    return '(?:' . implode('|', $out) . ')';
};

// List of common token(s) in code
$TOKEN = '(?:[!%&*\(\)\-=+\[\]\{\}|;:,.<>?\/])';
// Define root URL to be removed
$URL = $url->ground;

// Generate XML tag pattern
$XML = static function(string $tag, string $end = '>', $wrap = true): string {
    // <https://www.w3.org/TR/html5/syntax.html#elements-attributes>
    $tag = '(?:' . $tag . ')';
    return '(?:<' . $tag . '(?:\s[^>]*)?' . $end . ($wrap ? '[\s\S]*?<\/' . $tag . '>' : "") . ')';
};

$css = function(string $in, int $comment = 2, int $quote = 2) use(
    &$STRING,
    &$TOKEN,
    &$css_minify
): string {
    if ("" === ($in = trim($in))) {
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
    // Match CSS comment
    $COMMENT = '(?:\/\*[\s\S]*?\*\/)';
    // Match CSS string
    $S = $STRING('\'"', '\n');
    $out = "";
    $t = false;
    foreach (preg_split('/(' .
        // Prioritize string over comment because string cannot contains `\n` character
        $S . '|' .
        $COMMENT . '|' .
        $ANY . '|' .
        $ATTR . '|' .
        // Exclude `%`, `-` and '.' from token list
        strtr($TOKEN, [
            '%' => "",
            '\-' => "",
            '.' => ""
        ]) .
    ')/i', n($in), null, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) as $tok) {
        if (0 === strpos($tok, '/*') && '*/' === substr($tok, -2)) {
            if (
                1 === $comment || (
                    2 === $comment && (
                        // Detect special comment(s) from the third character
                        // It should be a `!` or `*` → `/*! keep */` or `/** keep */`
                        false !== strpos('!*', $tok[2]) ||
                        // Detect license comment(s) from the content
                        // It should contains character(s) like `@license`
                        false !== strpos($tok, '@licence') || // noun
                        false !== strpos($tok, '@license') || // verb
                        false !== strpos($tok, '@preserve')
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
        $t = "" === $out || false !== strpos('}:;,', substr($out, -1));
        // White-space only, skip!
        if ("" === $v) {
            // Do nothing!
        } else if ('*' === $v) {
            $out .= $tok;
        // String block, don’t touch!
        } else if (
            0 === strpos($v, '"') && '"' === substr($v, -1) ||
            0 === strpos($v, "'") && "'" === substr($v, -1)
        ) {
            // Remove quote(s) where possible
            if (2 === $quote && !is_numeric($v[1]) && preg_match('/^([\'"])(' . $KEY . ')\1$/i', $v, $m)) {
                $v = $m[2];
            }
            $out .= $v;
        // Maybe an attribute selector(s)
        } else if ('[' === $v[0] && ']' === substr($v, -1)) {
            $out .= preg_replace_callback('/=(' . $S . ')\s*([is])?/', function($m) use($KEY, $quote) {
                // Remove quote(s) where possible
                $v = $m[1];
                $v = 2 === $quote && !is_numeric($v[1]) && preg_match('/^([\'"])(' . $KEY . ')\1$/i', $v, $w) ? $w[2] : preg_replace('/([\'"])(.*?)\1/', '"$2"', $v);
                // Must return `[foo="bar"i]` or `[foo=bar i]`
                return trim('=' . $v . ('"' === $v[0] || "'" === $v[0] ? "" : ' ') . ($m[2] ?? ""));
            }, $tok);
        // Remove last `,` if token is `)`
        } else if (')' === $tok) {
            $out = rtrim($out, ',') . $tok;
        // Remove last white-space if token is `{`
        } else if ('{' === $tok) {
            $out = rtrim($out) . $tok;
        // Remove last `;` if token is `}`
        } else if ('}' === $tok) {
            $out = rtrim($out, ';') . $tok;
            // Remove empty selector
            if ('{}' === substr($out, -2)) {
                $out = explode('}', $out);
                array_pop($out);
                array_pop($out);
                // '}' concatenated to the end of the implode() function
                // to fix the problem with the closing brace not appearing
                // at the end of the CSS selector
                $out = implode('}', $out) . '}';
            }
        } else {
            $out .= $css_minify(trim($tok));
        }
    }
    return $out;
};

$css_minify = static function(string $in) use(
    &$NUMBER,
    &$STRING,
    &$URL,
    &$css_unit
): string {
    // Match character(s) starts from `calc(` to `)`
    $CALC = '(?:\s?\bcalc\([^}:;,]+\))';
    // Match HEX color code
    $HEX = '(?:\#(?:[a-f\d]{3}){1,2}\b)';
    // Match pseudo and class selector
    // Preserve single white-space around `:foo` to prevent
    // `article :focus` being minified into `article:focus`
    // Preserve single white-space around `.foo` to prevent
    // `article .foo` being minified into `article.foo`
    $PSEUDO = '(?:\s?[:.][a-z-][a-z\d-]*\b)';
    // Match CSS string
    $S = $STRING('\'"', '\n');
    // Match `src()` and `url()` function to minify the URL in it where possible
    $U = '(?:\s?\b(?:src|url)\(' . $S . '\))';
    // Match character(s) from `var(` to `)`
    $VAR = '(?:\s?\bvar\([^}:;,]+\)\s?)';
    $out = "";
    foreach (\preg_split('/(' .
        // Prioritize CSS function over pseudo selector to prevent `:calc`, `:var` match
        $CALC . '|' .
        $VAR . '|' .
        $U . '|' .
        $PSEUDO . '|' .
        $HEX .
    ')/i', $in, null, \PREG_SPLIT_DELIM_CAPTURE | \PREG_SPLIT_NO_EMPTY) as $tok) {
        $v = trim($tok);
        // White-space only, skip!
        if ("" === $v) {
            // Do nothing!
        // Maybe a calculation
        } else if (0 === strpos($v, 'calc(')) {
            $out .= $css_unit(preg_replace(['/\s+/', '/\s*([\(\)])\s*/'], [' ', '$1'], $tok));
        // Maybe a variable
        } else if (0 === strpos($v, 'var(')) {
            $out .= $css_unit(preg_replace('/\s*,\s*/', ',', $tok));
        // Maybe a URL function
        } else if (0 === strpos($v, 'src(') || 0 === strpos($v, 'url(')) {
            $tok = preg_replace_callback('/\s*(' . $S . ')\s*/', function($m) use($URL, $quote) {
                $v = substr(substr($m[1], 1), 0, -1);
                $v = strtr($v, [
                    $URL => ""
                ]); // Minify URL
                // Remove quote(s) where possible
                return 2 === $quote && strtr($v, '"\'>) ', '-----') === $v ? $v : '"' . $v . '"';
            }, $tok);
            $out .= $tok;
        // Maybe a HEX color code
        } else if (0 === strpos($v, '#') && (4 === strlen($v) || 7 === strlen($v)) && ctype_xdigit(substr($v, 1))) {
            // Minify HEX color code
            $out .= preg_replace('/([a-f\d])\1([a-f\d])\2([a-f\d])\3/i', '$1$2$3', strtolower($tok));
        // Maybe a pseudo and class selector or a value with its `:` prefix
        } else if (false !== strpos(':.', $v[0])) {
            $out .= $tok;
        } else {
            $out .= $css_unit($tok);
        }
    }
    return preg_replace([
        '/\s+/',
        '/:0(?: 0){1,3}([};,])/',
        '/\bbackground:(?:none|0)\b/',
        '/\b(border(?:-radius)?|outline):none\b/'
    ], [
        ' ',
        ':0$1',
        'background:0 0',
        '$1:0'
    ], $out);
};

$css_unit = static function(string $in) use(&$NUMBER): string {
    $out = preg_replace('/\s+/', ' ', $in);
    // Minify unit(s)
    return preg_replace_callback('/(' . $NUMBER . ')(%|Hz|ch|cm|deg|dpcm|dpi|dppx|em|ex|grad|in|kHz|mm|ms|pc|pt|px|rad|rem|s|turn|vh|vmax|vmin|vw)\b/', function($m) {
        $v = $m[1];
        $v = 0 === strpos($v, '-') ? '-' . ltrim(substr($v, 1), '0') : ltrim($v, '0');
        $v = 0 === strpos($v, '.') ? trim($v, '0') : $v;
        $v = '-' === $v || "" === $v ? '0' : $v;
        return $v . ('0' === $v ? "" : $m[2]);
    }, $out);
};

$html = function(string $in, int $comment = 2, int $quote = 1) use(
    &$XML,
    &$css,
    &$do_minify_embed_css,
    &$do_minify_embed_js,
    &$html_content,
    &$html_data,
    &$js,
    &$json
): string {
    if ("" === ($in = trim($in))) {
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
    foreach (preg_split('/(' .
        $COMMENT . '|' .
        $KEEP . '|' .
        // Match any HTML tag(s) include `<!DOCTYPE foo>` and `</foo>`
        $XML('[!\/]?[\w:-]+', '>', false) . '|' .
        $ENT .
    ')/i', n($in), null, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) as $tok) {
        $v = trim($tok);
        if (' ' === $tok) {
            // Fix case for `<button>Foo</button> <button>Bar</button>`
            // and `Foo bar baz <img src="smiley.gif"> qux`
            $out .= $tok; // Keep single white-space
            continue;
        } else if ("" === $v) {
            continue; // Skip multiple white-space(s) and single line-break
        // May be a HTML tag
        } else if ('<' === $v[0] && '>' === substr($v, -1)) {
            // This must be a HTML comment
            if (0 === strpos($v, '<!--') && '-->' === substr($v, -3)) {
                if (1 === $comment || (2 === $comment && '<![endif]-->' === substr($v, -12))) {
                    $out .= $v; // Keep legacy IE conditional comment(s)
                    continue;
                }
                continue; // Is a HTML comment, skip!
            }
            if ('</pre>' === substr($v, -6)) {
                $tok = $html_content($tok, 'pre');
            // Minify embedded JS code
            } else if ($do_minify_embed_js && '</script>' === substr($v, -9)) {
                $fn = false !== strpos($v, ' type=') && preg_match('/ type=([\'"])(application\/json)\1[ >]/', $v) ? $json : $js;
                $tok = $html_content($tok, 'script', $fn);
            // Minify embedded CSS code
            } else if ($do_minify_embed_css && '</style>' === substr($v, -8)) {
                $tok = $html_content($tok, 'style', $css);
            }
            $tok = $html_data($tok, $quote); // Is a HTML tag
            if (0 === strpos($tok, '</')) {
                $tok = P . $tok; // Is HTML close tag
            } else if (false === strpos($tok, '</')) {
                if (false === strpos(',img,input,', ',' . explode(' ', trim($tok, '<>'))[0] . ',')) {
                    $tok .= P; // Is HTML open tag
                }
            }
            $out .= $tok;
        } else if ('&' === $v[0] && ';' === substr($v, -1) && '&lt;' !== $v && '&gt;' !== $v && '&amp;' !== $v) {
            $out .= html_entity_decode($v); // Evaluate HTML entity
        } else {
            $out .= $tok; // Other(s)
        }
    }
    // Clean up!
    if (false !== strpos($out, P)) {
        // `<tag>[remove white-space here]foo bar baz[remove white-space here]</tag>`
        $out = preg_replace(['/\s*' . P . '</', '/>' . P . '\s*/'], ['<', '>'], $out);
    }
    return $out;
};

$html_content = static function(string $in, string $t, $fn = false): string {
    return preg_replace_callback('/<' . $t . '(\s[^>]*)?>\s*([\s\S]*?)\s*<\/' . $t . '>/', function($m) use($fn, $t) {
        return '<' . $t . $m[1] . '>' . ($fn ? $fn($m[2]) : $m[2]) . '</' . $t . '>';
    }, $in);
};

$html_data = static function(string $in, int $quote) use(
    &$KEY,
    &$STRING,
    &$URL,
    &$css
): string {
    return (
        false === strpos($in, ' ') &&
        false === strpos($in, "\n") &&
        false === strpos($in, "\t")
    ) ? $in : preg_replace_callback('/<([^>\/\s]+)\s*(\s[^>]+?)?\s*>/', function($m) use(
        $STRING,
        $URL,
        $css,
        $quote
    ) {
        $KEY = '[a-z_-][a-z\d_-]*';
        if (isset($m[2])) {
            // Minify CSS inline code
            if (false !== strpos($m[2], ' style=')) {
                $m[2] = preg_replace_callback('/( style=)(' . $STRING() . ')/', function($m) use($css) {
                    $q = $m[2][0];
                    return $m[1] . $q . $css(substr($m[2], 1, -1)) . $q;
                }, $m[2]);
            }
            // Minify URL in attribute value
            if (false !== strpos($m[2], '=') && false !== strpos($m[2], '://')) {
                $m[2] = strtr($m[2], [
                    '="' . $URL . '"' => '="/"',
                    "='" . $URL . "'" => "='/'",
                    '="' . $URL => '="',
                    "='" . $URL => "='"
                ]);
            }
            $out = '<' . $m[1] . preg_replace([
                // From `a="a"`, `a='a'`, `a="true"`, `a='true'`, `a=""` and `a=''` to `a` [^1]
                '/\s(a(sync|uto(focus|play))|c(hecked|ontrols)|d(efer|isabled)|hidden|ismap|loop|multiple|open|re(adonly|quired)|s((cop|elect)ed|pellcheck))(?:=([\'"]?)(?:true|\1)?\2)/',
                // Remove extra white–space(s) between HTML attribute(s) [^2]
                '/\s*([^\s=]+)(=(?:\S+|([\'"]?).*?\3)|$)/',
                // From `<foo />` to `<foo/>` [^3]
                '/\s+\/$/'
            ], [
                // [^1]
                ' $1',
                // [^2]
                ' $1$2',
                // [^3]
                '/'
            ], strtr($m[2], "\n", ' ')) . '>';
            return 2 === $quote ? preg_replace('/=([\'"])(' . $KEY . ')\1/i', '=$2', $out) : $out;
        }
        return '<' . $m[1] . '>';
    }, $in);
};

$js = function(string $in, int $comment = 2, int $quote = 2) use(
    &$STRING,
    &$NUMBER,
    &$TOKEN
): string {
    if ("" === ($in = trim($in))) {
        return "";
    }
    // Match JS comment inline
    $COMMENT = '(?:\/\/[^\n]*)';
    // Match JS comment block
    $COMMENT_DOC = '(?:\/\*[\s\S]*?\*\/)';
    $KEY = '(?:[a-z$_][a-z\d$_]*)';
    $LITERAL = '(?:\b(?:true|false)\b)';
    $REGEX = '(?:' . strtr($STRING('/', '\n'), ['/' => "\\/"]) . '[gimuy]*[;,.\s])';
    $K = ['true' => '!0', 'false' => '!1'];
    $out = "";
    foreach (preg_split('/\s*(' .
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
    ')\s*/', n($in), null, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) as $tok) {
        if (0 === strpos($tok, '//')) {
            continue; // Is a comment, skip!
        }
        if (0 === strpos($tok, '/*') && '*/' === substr($tok, -2)) {
            if (
                1 === $comment || (
                    2 === $comment && (
                        // Detect special comment(s) from the third character
                        // It should be a `!` or `*` → `/*! keep */` or `/** keep */`
                        false !== strpos('!*', $tok[2]) ||
                        // Detect license comment(s) from the content
                        // It should contains character(s) like `@license`
                        false !== strpos($tok, '@licence') || // noun
                        false !== strpos($tok, '@license') || // verb
                        false !== strpos($tok, '@preserve')
                    )
                )
            ) {
                $out .= $tok;
                continue;
            }
            continue; // Is a comment, skip!
        // String block, don’t touch!
        } else if (
            0 === strpos($tok, '"') && '"' === substr($tok, -1) ||
            0 === strpos($tok, "'") && "'" === substr($tok, -1) ||
            0 === strpos($tok, '`') && '`' === substr($tok, -1)
        ) {
            $out .= $tok;
        // Maybe a pattern
        } else if ('/' === $tok[0] && preg_match('/^\/.+\/[a-z]*[;,.\s]$/', $tok)) {
            $out .= trim($tok);
        } else if (is_numeric($tok)) {
            $tok = 0 === strpos($tok, '-') ? '-' . ltrim(substr($tok, 1), '0') : ltrim($tok, '0');
            $tok = 0 === strpos($tok, '.') ? trim($tok, '0') : $tok;
            $out .= "" === $tok ? '0' : $tok; // Minify number(s)
        } else {
            if (']' === $tok) {
                $out = rtrim($out, ',') . $tok; // Remove the last comma
            } else if ('}' === $tok) {
                $out = rtrim($out, ';,') . $tok; // Remove the last semi-colon and comma
            } else {
                // Hot fix(es)
                if (
                    'case' === $tok ||
                    'return' === $tok ||
                    'return void' === $tok ||
                    'void' === $tok
                ) {
                    $tok .= ' ';
                }
                $out .= $K[$tok] ?? $tok;
            }
        }
    }
    return 1 !== $quote ? preg_replace([
        // Minify object property [^1]
        '/([{,])([\'"])(' . $KEY . ')\2:/',
        // Minify object access [^2]
        '/(' . $KEY . '|\])\[([\'"])(' . $KEY . ')\2\]/'
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
$php = function(string $in, int $comment = 2, int $quote = 1): string {
    $out = "";
    // White-space(s) around these token(s) can be ignored
    static $t = [
        T_AND_EQUAL => 1,                // &=
        T_ARRAY_CAST => 1,               // (array)
        T_BOOL_CAST => 1,                // (bool) and (boolean)
        T_BOOLEAN_AND => 1,              // &&
        T_BOOLEAN_OR => 1,               // ||
        T_COALESCE => 1,                 // ??
        T_CONCAT_EQUAL => 1,             // .=
        T_DEC => 1,                      // --
        T_DIV_EQUAL => 1,                // /=
        T_DOLLAR_OPEN_CURLY_BRACES => 1, // ${
        T_DOUBLE_ARROW => 1,             // =>
        T_DOUBLE_CAST => 1,              // (double) or (float) or (real)
        T_DOUBLE_COLON => 1,             // ::
        T_INC => 1,                      // ++
        T_INT_CAST => 1,                 // (int) or (integer)
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
        T_STRING_CAST => 1,              // (string)
        T_XOR_EQUAL => 1                 // ^=
    ];
    $c = count($toks = token_get_all($in));
    $doc = $skip = false;
    $begin = $end = null;
    for ($i = 0; $i < $c; ++$i) {
        $tok = $toks[$i];
        if (is_array($tok)) {
            $id = $tok[0];
            $value = $tok[1];
            if (T_INLINE_HTML === $id) {
                $out .= $value;
                $skip = false;
            } else {
                if (T_OPEN_TAG === $id) {
                    if (
                        false !== strpos($value, ' ') ||
                        false !== strpos($value, "\n") ||
                        false !== strpos($value, "\t") ||
                        false !== strpos($value, "\r")
                    ) {
                        $value = rtrim($value);
                    }
                    $out .= $value . ' ';
                    $begin = T_OPEN_TAG;
                    $skip = true;
                } else if (T_OPEN_TAG_WITH_ECHO === $id) {
                    $out .= $value;
                    $begin = T_OPEN_TAG_WITH_ECHO;
                    $skip = true;
                } else if (T_CLOSE_TAG === $id) {
                    if (T_OPEN_TAG_WITH_ECHO === $begin) {
                        $out = rtrim($out, '; ');
                    } else {
                        $value = ' ' . $value;
                    }
                    $out .= $value;
                    $begin = null;
                    $skip = false;
                } else if (isset($t[$id])) {
                    $out .= $value;
                    $skip = true;
                } else if (T_ENCAPSED_AND_WHITESPACE === $id || T_CONSTANT_ENCAPSED_STRING === $id) {
                    if ('"' === $value[0]) {
                        $value = addcslashes($value, "\n\r\t");
                    }
                    $out .= $value;
                    $skip = true;
                } else if (T_WHITESPACE === $id) {
                    $n = $toks[$i + 1] ?? null;
                    if(!$skip && (!is_string($n) || '$' === $n) && !isset($t[$n[0]])) {
                        $out .= ' ';
                    }
                    $skip = false;
                } else if (T_START_HEREDOC === $id) {
                    $out .= "<<<S\n";
                    $skip = false;
                    $doc = true; // Enter HEREDOC
                } else if (T_END_HEREDOC === $id) {
                    $out .= "S;\n";
                    $skip = true;
                    $doc = false; // Exit HEREDOC
                    for ($j = $i + 1; $j < $c; ++$j) {
                        if (is_string($toks[$j]) && ';' === $toks[$j]) {
                            $i = $j;
                            break;
                        } else if (T_CLOSE_TAG === $toks[$j][0]) {
                            break;
                        }
                    }
                } else if (T_COMMENT === $id || T_DOC_COMMENT === $id) {
                    if (
                        1 === $comment || (
                            2 === $comment && (
                                // Detect special comment(s) from the third character
                                // It should be a `!` or `*` → `/*! keep */` or `/** keep */`
                                !empty($value[2]) && false !== strpos('!*', $value[2]) ||
                                // Detect license comment(s) from the content
                                // It should contains character(s) like `@license`
                                false !== strpos($value, '@licence') || // noun
                                false !== strpos($value, '@license') || // verb
                                false !== strpos($value, '@preserve')
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
            if (false === strpos(';:', $tok) || $end !== $tok) {
                $out .= $tok;
                $end = $tok;
            }
            $skip = true;
        }
    }
    return $out;
};

$state = State::get('x.minify', true);
array_unshift($state['.css'], "");
array_unshift($state['.html'], "");
array_unshift($state['.js'], "");
array_unshift($state['.json'], "");
array_unshift($state['.php'], "");
Minify::_('.css', [$css, $state['.css']]);
Minify::_('.html', [$html, $state['.html']]);
Minify::_('.js', [$js, $state['.js']]);
Minify::_('.json', [$json, $state['.json']]);
Minify::_('.php', [$php, $state['.php']]);
