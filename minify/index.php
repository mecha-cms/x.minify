<?php namespace x;

require __DIR__ . \D . 'engine' . \D . 'use.php';

if (\defined("\\TEST") && 'x.minify' === \TEST) {
    require __DIR__ . \D . 'test.php';
}

function minify($content) {
    return \Minify::HTML($content);
}

\Hook::set('content', __NAMESPACE__ . "\\minify", 2);