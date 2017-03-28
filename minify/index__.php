<?php

function fn_minify_replace($content) {
    $state = Extend::state(__DIR__);
    // Minify embedded CSS
    if (stripos($content, '</style>') !== false) {
        $content = preg_replace_callback('#<style(\s[^<>]*?)?>([\s\S]*?)<\/style>#i', function($m) use($state) {
            array_unshift($state['css'], $m[2]);
            return '<style' . $m[1] . '>' . call_user_func_array('Minify::css', $state['css']) . '</style>';
        }, $content);
    }
    // Minify embedded JS
    if (stripos($content, '</script>') !== false) {
        $content = preg_replace_callback('#<script(\s[^<>]*?)?>([\s\S]*?)<\/script>#i', function($m) use($state) {
            array_unshift($state['js'], $m[2]);
            return '<script' . $m[1] . '>' . call_user_func_array('Minify::js', $state['js']) . '</script>';
        }, $content);
    }
    // Minify HTML
    array_unshift($state['html'], $content);
    $content = call_user_func_array('Minify::html', $state['html']);
    return $content;
}

Hook::set('shield.output', 'fn_minify_replace', 2);