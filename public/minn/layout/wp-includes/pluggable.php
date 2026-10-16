<?php
// Minn Engine: the reference defines its pluggable functions in this file once the plugins have loaded, and a plugin that requires it before then (to call wp_generate_password early) gets them at once; requiring it does the same with the engine's.
if (defined('MINN_ENGINE_DIR')) {
    require_once MINN_ENGINE_DIR . '/wp-api/pluggable.php';
}
