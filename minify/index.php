<?php namespace x;

function minify($content) {
    return \Minify::HTML($content);
}

\Hook::set('content', __NAMESPACE__ . "\\minify", 2);

require __DIR__ . DS . 'engine' . DS . 'plug' . DS . '_todo.php';