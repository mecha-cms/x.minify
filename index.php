<?php namespace x\minify;

require __DIR__ . \D . 'index' . \D . 'c-s-s.php';
require __DIR__ . \D . 'index' . \D . 'h-t-m-l.php';
require __DIR__ . \D . 'index' . \D . 'j-s.php';
require __DIR__ . \D . 'index' . \D . 'j-s-o-n.php';
require __DIR__ . \D . 'index' . \D . 'p-h-p.php';
require __DIR__ . \D . 'index' . \D . 'x-m-l.php';

function content($content) {
    if ($type = \type()) {
        if ('text/css' === $type) {
            return \Minify::CSS($content);
        }
        if ('text/html' === $type) {
            return \Minify::HTML($content);
        }
        if (
            'application/javascript' === $type ||
            'application/x-javascript' === $type ||
            'text/javascript' === $type
        ) {
            return \Minify::JS($content);
        }
        if (
            'application/feed+json' === $type ||
            'application/geo+json' === $type ||
            'application/json' === $type ||
            'application/ld+json' === $type ||
            'text/json' === $type
        ) {
            return \Minify::JSON($content);
        }
        if (
            'application/atom+xml' === $type ||
            'application/mathml+xml' === $type ||
            'application/rdf+xml' === $type ||
            'application/rss+xml' === $type ||
            'image/svg+xml' === $type ||
            'text/xml' === $type
        ) {
            return \Minify::XML($content);
        }
    }
    if ($x = \pathinfo(\lot('url')->path ?? "", \PATHINFO_EXTENSION)) {
        if ('css' === $x) {
            return \Minify::CSS($content);
        }
        if (
            'htm' === $x ||
            'html' === $x
        ) {
            return \Minify::HTML($content);
        }
        if (
            'js' === $x ||
            'mjs' === $x
        ) {
            return \Minify::JS($content);
        }
        if (
            'json' === $x ||
            'jsonp' === $x ||
            'webmanifest' === $x
        ) {
            return \Minify::JSON($content);
        }
        if (
            'svg' === $x ||
            'xht' === $x ||
            'xhtm' === $x ||
            'xhtml' === $x ||
            'xml' === $x
        ) {
            return \Minify::XML($content);
        }
    }
    return $content;
}

\Hook::set('content', __NAMESPACE__ . "\\content", 2);