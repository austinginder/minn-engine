<?php
// Minn Engine: the reference loads WP_Site only when asked, so a single site has none until a plugin requires this file; requiring it loads the engine's.
if (defined('MINN_ENGINE_DIR')) {
    require_once MINN_ENGINE_DIR . '/wp-api/classes/network/WP_Site.php';
}
