<?php namespace x\minify;

function content($content) {
    return \Minify::HTML($content);
}

\Hook::set('content', __NAMESPACE__ . "\\content", 2);

if (\defined("\\TEST") && 'x.minify' === \TEST && \is_file($test = __DIR__ . \D . 'test.php')) {
    require $test;
}