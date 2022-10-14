<?php

foreach ([
    'CSS' => 'css',
    'HTML' => 'html',
    'JS' => 'js',
    'JSON' => 'json',
    'PHP' => 'php'
] as $k => $v) {
    if ("" === ($content = file_get_contents(__DIR__ . D . 'test' . D . $v))) {
        continue;
    }
    echo '<h2>' . $k . '</h2>';
    echo '<pre style="background:#ccc;border:1px solid rgba(0,0,0,.25);color:#000;font:normal normal 100%/1.25 monospace;padding:.5em .75em;white-space:pre-wrap;word-wrap:break-word;">' . htmlspecialchars($content) . '</pre>';
    echo '<pre style="background:#cfc;border:1px solid rgba(0,0,0,.25);color:#000;font:normal normal 100%/1.25 monospace;padding:.5em .75em;white-space:pre-wrap;word-wrap:break-word;">' . htmlspecialchars(call_user_func('Minify::' . $k, $content)) . '</pre>';
}

exit;