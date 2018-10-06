<?php

class Minify extends Genome {

    const STRING = '"(?:[^"\\\]|\\\.)*"|\'(?:[^\'\\\]|\\\.)*\'|`(?:[^`\\\]|\\\.)*`';

    const COMMENT_CSS = '/\*[\s\S]*?\*/';
    const COMMENT_HTML = '<!\-{2}[\s\S]*?\-{2}>';
    const COMMENT_JS = '//[^\n]*';
    const COMMENT_SH = '\#[^\n]*';

    const PATTERN_JS = '/[^\n]+?/[gimuy]*';

    const HTML = '<[!/]?[a-zA-Z\d:.-]+[\s\S]*?>';
    const HTML_ENT = '&(?:[a-zA-Z\d]+|\#\d+|\#x[a-fA-F\d]+);';
    const HTML_KEEP = '<pre(?:\s[^<>]*?)?>[\s\S]*?</pre>|<code(?:\s[^<>]*?)?>[\s\S]*?</code>|<script(?:\s[^<>]*?)?>[\s\S]*?</script>|<style(?:\s[^<>]*?)?>[\s\S]*?</style>|<textarea(?:\s[^<>]*?)?>[\s\S]*?</textarea>';

}