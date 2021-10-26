<?php namespace x;

function minify($content) {
    return \Minify::HTML($content);
}

\Hook::set('content', __NAMESPACE__ . "\\minify", 2);

if (\defined("\\DEBUG") && 'x.minify' === \DEBUG) {
    require __DIR__ . \DS . 'test.php';
}