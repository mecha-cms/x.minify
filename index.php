<?php namespace x;

function minify($content) {
    return \Minify::HTML($content);
}

\Hook::set('content', __NAMESPACE__ . "\\minify", 2);

if (\defined("\\TEST") && 'x.minify' === \TEST && \is_file($test = __DIR__ . \D . 'test.php')) {
    require $test;
}