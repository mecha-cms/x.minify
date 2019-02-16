<?php namespace fn;

function minify($content) {
    return \Minify::HTML($content);
}

\Hook::set('shield.yield', __NAMESPACE__ . "\\minify", 2);