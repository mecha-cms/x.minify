<?php namespace x\minify;

require __DIR__ . \D . 'index' . \D . 'c-s-s.php';
require __DIR__ . \D . 'index' . \D . 'h-t-m-l.php';
require __DIR__ . \D . 'index' . \D . 'j-s.php';
require __DIR__ . \D . 'index' . \D . 'j-s-o-n.php';
require __DIR__ . \D . 'index' . \D . 'p-h-p.php';
require __DIR__ . \D . 'index' . \D . 'x-m-l.php';

function content($content) {
    return \Minify::HTML($content);
}

\Hook::set('content', __NAMESPACE__ . "\\content", 2);