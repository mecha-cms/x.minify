<?php

foreach ([
    'CSS' => 'css',
    'HTML' => 'html',
    'JS' => 'js',
    'JSON' => 'json',
    'PHP' => 'php'
] as $k => $v) {
    if ("" === ($content = file_get_contents(__DIR__ . DS . 'test' . DS . $v))) {
        continue;
    }
    echo '<h2>' . $k . '</h2>';
    echo '<pre style="border:3px solid #900;padding:3px;white-space:pre-wrap;word-wrap:break-word;"><code>' . htmlspecialchars($content) . '</code></pre>';
    echo '<pre style="border:3px solid #090;padding:3px;white-space:pre-wrap;word-wrap:break-word;"><code>' . htmlspecialchars(call_user_func('Minify::' . $k, $content)) . '</code></pre>';
}

exit;