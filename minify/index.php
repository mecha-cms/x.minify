<?php namespace _;

function minify($content) {
    return \Minify::HTML($content);
}

\Hook::set('content', __NAMESPACE__ . "\\minify", 2);