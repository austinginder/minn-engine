<?php
// The script modules registry hooks its printing into the head and footer,
// and the interactivity runtime hands its state and config to the client.

add_action('init', 'wp_default_script_modules', 0);
wp_script_modules()->add_hooks();
wp_interactivity()->add_hooks();
