<?php
/**
 * Third behaviour probe down the catalogue queue, from the top of it: the
 * small helpers the most-installed plugins were one or two names short of
 * (Elementor, WPForms, Jetpack, WP Super Cache, EWWW, SiteGround, Classic
 * Editor, All-in-One WP Migration). Same protocol as api-probe.php.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
foreach (['home_url', 'site_url', 'option_home', 'option_siteurl'] as $devHook) {
    remove_all_filters($devHook);
}
if (defined('ABSPATH')) {
    foreach (['misc', 'plugin', 'file'] as $inc) {
        if (is_file(ABSPATH . 'wp-admin/includes/' . $inc . '.php')) {
            require_once ABSPATH . 'wp-admin/includes/' . $inc . '.php';
        }
    }
}
add_filter('doing_it_wrong_trigger_error', '__return_false');
add_filter('deprecated_function_trigger_error', '__return_false');
$notices = [];
add_action('doing_it_wrong_run', static function ($function, $message, $version) use (&$notices): void {
    $notices[] = ['wrong', (string) $function, trim(strip_tags((string) $message)), (string) $version];
}, 10, 3);
add_action('deprecated_function_run', static function ($function, $replacement, $version) use (&$notices): void {
    $notices[] = ['deprecated', (string) $function, (string) $replacement, (string) $version];
}, 10, 3);
$heard = static function () use (&$notices): array {
    $out = $notices;
    $notices = [];
    return $out;
};

// --- convert_invalid_entities: the Windows-1252 numeric references, both
// spellings, and the ones around them that must be left alone.
$cp = [];
for ($code = 120; $code <= 165; $code++) {
    $cp[] = convert_invalid_entities("&#{$code};");
}
$say('convert_invalid_entities decimal 120-165', $cp);
$hex = [];
for ($code = 0x7e; $code <= 0xa2; $code++) {
    $hex[] = convert_invalid_entities(sprintf('&#x%x;|&#X%X;|&#x%02X;', $code, $code, $code));
}
$say('convert_invalid_entities hex 7e-a2', $hex);
$say('convert_invalid_entities mixed', [
    convert_invalid_entities('Price &#128;5 &amp; a &#150; dash, &#8364; already, &#0128; padded'),
    convert_invalid_entities('&#128'),
    convert_invalid_entities('&#x80'),
    convert_invalid_entities('plain text'),
    convert_invalid_entities(''),
    convert_invalid_entities('&#00000150;'),
    convert_invalid_entities('&#x0080;'),
]);

// --- wp_is_valid_utf8: well-formed text, then each way bytes go wrong.
$utf8 = [
    'empty' => '',
    'ascii' => 'just a test',
    'nul' => "a\x00b",
    'two byte' => "caf\xC3\xA9",
    'three byte' => "\xE2\x82\xAC",
    'four byte' => "\xF0\x9F\x98\x80",
    'max scalar' => "\xF4\x8F\xBF\xBF",
    'beyond max' => "\xF4\x90\x80\x80",
    'noncharacter fffe' => "\xEF\xBF\xBE",
    'noncharacter ffff' => "\xEF\xBF\xBF",
    'surrogate high' => "\xED\xA0\x80",
    'surrogate low' => "\xED\xBF\xBF",
    'overlong slash' => "\xC0\xAF",
    'overlong two' => "\xC1\xBF",
    'overlong three' => "\xE0\x80\xAF",
    'overlong four' => "\xF0\x80\x80\xAF",
    'lone continuation' => "\x80",
    'truncated three' => "\xE2\x82",
    'truncated four' => "\xF0\x9F\x98",
    'five byte' => "\xF8\x88\x80\x80\x80",
    'fe' => "\xFE",
    'ff' => "\xFF",
    'f5' => "\xF5\x80\x80\x80",
    'latin1' => "caf\xE9",
    'bom' => "\xEF\xBB\xBFtext",
    'continuation after ascii' => "a\x80b",
];
$verdicts = [];
foreach ($utf8 as $name => $bytes) {
    $verdicts[$name] = wp_is_valid_utf8($bytes);
}
$say('wp_is_valid_utf8', $verdicts);

// --- wp_remove_surrounding_empty_script_tags: a buffered inline script, and
// every shape that is not one.
$scripts = [
    "<script>alert(1)</script>",
    "<script>\nalert(1);\n</script>",
    "  <script>x</script>  ",
    "\n\t<script>\n\tx\n\t</script>\n",
    "<script type=\"text/javascript\">x</script>",
    "<SCRIPT>x</SCRIPT>",
    "<script></script>",
    "<script>a</script><script>b</script>",
    "x",
    "<script>x",
    "x</script>",
    "<div>x</div>",
    "<script >x</script >",
    "<script> </script>",
    "<script>\n</script>",
    "<Script>x</sCrIpT>",
    "<script>x</script>\n\n",
];
$stripped = [];
foreach ($scripts as $script) {
    $stripped[] = [wp_remove_surrounding_empty_script_tags($script), $heard()];
}
$say('wp_remove_surrounding_empty_script_tags', $stripped);

// --- wp_autoload_values_to_autoload, then through its filter.
$say('wp_autoload_values_to_autoload', wp_autoload_values_to_autoload());
$autoloadArgs = null;
$autoloadFilter = static function ($values) use (&$autoloadArgs) {
    $autoloadArgs = func_get_args();
    return array_merge($values, ['minn']);
};
add_filter('wp_autoload_values_to_autoload', $autoloadFilter);
$say('wp_autoload_values_to_autoload filtered', [wp_autoload_values_to_autoload(), $autoloadArgs]);
remove_filter('wp_autoload_values_to_autoload', $autoloadFilter);
add_filter('wp_autoload_values_to_autoload', '__return_empty_array');
$say('wp_autoload_values_to_autoload emptied', wp_autoload_values_to_autoload());
remove_filter('wp_autoload_values_to_autoload', '__return_empty_array');
foreach ([['auto', 'yes'], ['on'], ['yes', 'yes', 'no'], ['no', 'yes']] as $i => $returned) {
    $replace = static fn () => $returned;
    add_filter('wp_autoload_values_to_autoload', $replace);
    $say("wp_autoload_values_to_autoload replaced {$i}", wp_autoload_values_to_autoload());
    remove_filter('wp_autoload_values_to_autoload', $replace);
}

// --- wp_clone.
$original = new stdClass();
$original->a = 1;
$original->inner = new stdClass();
$copy = wp_clone($original);
$copy->a = 2;
$say('wp_clone', [get_class($copy), $copy !== $original, $original->a, $copy->a, $copy->inner === $original->inner, $heard()]);

// --- wp_get_comment_fields_max_lengths, and its filter.
$say('wp_get_comment_fields_max_lengths', wp_get_comment_fields_max_lengths());
$lengthsFilter = static function ($lengths) {
    $lengths['comment_author'] = 50;
    return $lengths;
};
add_filter('wp_get_comment_fields_max_lengths', $lengthsFilter);
$say('wp_get_comment_fields_max_lengths filtered', wp_get_comment_fields_max_lengths());
remove_filter('wp_get_comment_fields_max_lengths', $lengthsFilter);

// --- wp_kses_uri_attributes, and its filter.
$say('wp_kses_uri_attributes', wp_kses_uri_attributes());
$uriFilter = static function ($attributes) {
    $attributes[] = 'data-minn';
    return $attributes;
};
add_filter('wp_kses_uri_attributes', $uriFilter);
$say('wp_kses_uri_attributes filtered', wp_kses_uri_attributes());
remove_filter('wp_kses_uri_attributes', $uriFilter);

// --- wp_high_priority_element_flag: the setter's return, the getter, and
// whether an explicit high-priority image spends it.
$say('wp_high_priority_element_flag initial', wp_high_priority_element_flag());
$say('wp_high_priority_element_flag set false', wp_high_priority_element_flag(false));
$say('wp_high_priority_element_flag after false', wp_high_priority_element_flag());
$say('wp_high_priority_element_flag explicit image while false', wp_get_loading_optimization_attributes('img', ['width' => 1000, 'height' => 800, 'fetchpriority' => 'high'], 'wp_get_attachment_image'));
$say('wp_high_priority_element_flag set true', wp_high_priority_element_flag(true));
$say('wp_high_priority_element_flag explicit image while true', wp_get_loading_optimization_attributes('img', ['width' => 1000, 'height' => 800, 'fetchpriority' => 'high'], 'wp_get_attachment_image'));
$say('wp_high_priority_element_flag after explicit image', wp_high_priority_element_flag());
$say('wp_high_priority_element_flag second explicit image', wp_get_loading_optimization_attributes('img', ['width' => 1000, 'height' => 800, 'fetchpriority' => 'high'], 'wp_get_attachment_image'));
$nonBool = [];
foreach ([0, 1, 'yes', '', null, [], 'false'] as $value) {
    wp_high_priority_element_flag(true);
    $first = wp_high_priority_element_flag($value);
    $nonBool[] = [$first, wp_high_priority_element_flag()];
    wp_high_priority_element_flag(false);
    $first = wp_high_priority_element_flag($value);
    $nonBool[] = [$first, wp_high_priority_element_flag()];
}
$say('wp_high_priority_element_flag non-bool', $nonBool);
wp_high_priority_element_flag(true);
$say('wp_high_priority_element_flag eager image while true', [wp_get_loading_optimization_attributes('img', ['width' => 1000, 'height' => 800, 'loading' => false], 'wp_get_attachment_image'), wp_high_priority_element_flag()]);
$say('wp_high_priority_element_flag eager image after', [wp_get_loading_optimization_attributes('img', ['width' => 1000, 'height' => 800, 'loading' => false], 'wp_get_attachment_image'), wp_high_priority_element_flag()]);
wp_high_priority_element_flag(false);
$say('wp_high_priority_element_flag eager image while false', [wp_get_loading_optimization_attributes('img', ['width' => 1000, 'height' => 800, 'loading' => false], 'wp_get_attachment_image'), wp_high_priority_element_flag()]);
$say('wp_high_priority_element_flag lazy image while false', wp_get_loading_optimization_attributes('img', ['width' => 1000, 'height' => 800], 'wp_get_attachment_image'));
wp_high_priority_element_flag(true);

// --- _get_dropins.
$say('_get_dropins', _get_dropins());

// --- get_post_mime_types, and its filter.
$say('get_post_mime_types', get_post_mime_types());
$mimeFilter = static function ($types) {
    unset($types['archive']);
    return $types;
};
add_filter('post_mime_types', $mimeFilter);
$say('get_post_mime_types filtered', array_keys(get_post_mime_types()));
remove_filter('post_mime_types', $mimeFilter);

// --- extract_from_markers over files insert_with_markers wrote and files a
// person wrote by hand.
$dir = sys_get_temp_dir() . '/minn-probe-markers-' . getmypid();
@mkdir($dir);
$file = $dir . '/.htaccess';
$say('extract_from_markers missing file', extract_from_markers($dir . '/nope', 'WordPress'));
file_put_contents($file, "# top\n");
insert_with_markers($file, 'Minn', ['RewriteEngine On', '', '  RewriteBase /  ']);
$say('extract_from_markers written', extract_from_markers($file, 'Minn'));
$say('extract_from_markers other marker', extract_from_markers($file, 'WordPress'));
file_put_contents($file, "# BEGIN A\nline one\r\nline two  \n\n# END A\n# BEGIN B\n# BEGIN A\nnested\n# END A\n# END B\n#BEGIN C\nno space\n#END C\n# BEGIN D\nunterminated\n");
$say('extract_from_markers hand A', extract_from_markers($file, 'A'));
$say('extract_from_markers hand B', extract_from_markers($file, 'B'));
$say('extract_from_markers hand C', extract_from_markers($file, 'C'));
$say('extract_from_markers hand D', extract_from_markers($file, 'D'));
file_put_contents($file, "  # BEGIN E\nindented\n  # END E\n# BEGIN E \ntrailing\n# END E \n");
$say('extract_from_markers spacing', extract_from_markers($file, 'E'));
file_put_contents($file, "# BEGIN F\nfirst\n# END F\n# BEGIN F\nsecond\n# END F\n");
$say('extract_from_markers repeated', extract_from_markers($file, 'F'));
file_put_contents($file, "# BEGIN GG\nwider\n# END GG\n# BEGIN G\nexact\n# END G\n");
$say('extract_from_markers prefix', extract_from_markers($file, 'G'));
file_put_contents($file, "text # BEGIN H\nafter text\n# END H\n# BEGIN H trailing\nafter trailing\n# END H\n");
$say('extract_from_markers inline', extract_from_markers($file, 'H'));
file_put_contents($file, "# BEGIN I\n# a comment\n  # indented comment\nRewriteRule ^x$ - [L] # trailing comment\n#\nkept\n# END I\n");
$say('extract_from_markers comments', extract_from_markers($file, 'I'));
file_put_contents($file, "# END J\nbefore\n# BEGIN J\ninside\n# END J\nafter\n");
$say('extract_from_markers end first', extract_from_markers($file, 'J'));
file_put_contents($file, "# begin K\nlower\n# end K\n");
$say('extract_from_markers case', extract_from_markers($file, 'K'));
file_put_contents($file, "# BEGIN L\r\ncrlf\r\n# END L\r\n");
$say('extract_from_markers crlf', extract_from_markers($file, 'L'));

// --- apache_mod_loaded off Apache, then got_mod_rewrite and what its filter is handed.
$say('apache_mod_loaded', [apache_mod_loaded('mod_rewrite'), apache_mod_loaded('mod_rewrite', true), apache_mod_loaded('mod_rewrite', false)]);
$gotArgs = null;
$gotFilter = static function () use (&$gotArgs) {
    $gotArgs = func_get_args();
    return $gotArgs[0];
};
add_filter('got_rewrite', $gotFilter, 10, 9);
$say('got_mod_rewrite', [got_mod_rewrite(), $gotArgs]);
remove_filter('got_rewrite', $gotFilter, 10);
add_filter('got_rewrite', '__return_false');
$say('got_mod_rewrite filtered', got_mod_rewrite());
remove_filter('got_rewrite', '__return_false');

// --- saveDomDocument.
$doc = new DOMDocument();
$doc->loadXML('<?xml version="1.0" encoding="UTF-8"?><configuration><system.webServer><rewrite/></system.webServer></configuration>');
$target = $dir . '/web.config';
$saved = saveDomDocument($doc, $target);
$say('saveDomDocument', [$saved, file_get_contents($target)]);
$pretty = new DOMDocument();
$pretty->preserveWhiteSpace = false;
$pretty->formatOutput = true;
$pretty->loadXML('<configuration><a><b x="1"/></a></configuration>');
saveDomDocument($pretty, $target);
$say('saveDomDocument formatted', file_get_contents($target));
// An unwritable target is not pinned: the reference fatals on it (a TypeError
// from writing to the failed handle).
foreach (glob($dir . '/{,.}*', GLOB_BRACE) ?: [] as $leftover) {
    if (is_file($leftover)) {
        unlink($leftover);
    }
}
@rmdir($dir);

// --- add_option_whitelist and add_allowed_options.
$say('add_option_whitelist', [add_option_whitelist(['minn_group' => ['minn_a', 'minn_b']], ['minn_group' => ['minn_0', 'minn_a'], 'other' => ['x']]), $heard()]);
$say('add_allowed_options', [add_allowed_options(['minn_group' => ['minn_c'], 'fresh' => ['y']], ['minn_group' => ['minn_0']]), $heard()]);
$say('add_allowed_options string option', add_allowed_options(['minn_group' => 'minn_d'], ['minn_group' => ['minn_0']]));
$GLOBALS['allowed_options'] = ['global_group' => ['g1']];
$say('add_allowed_options global', [add_allowed_options(['global_group' => ['g2']]), $GLOBALS['allowed_options']]);
unset($GLOBALS['allowed_options']);

// --- wp_is_recovery_mode.
$say('wp_is_recovery_mode', wp_is_recovery_mode());

// --- wp_supports_ai (WP_AI_SUPPORT is not defined on either stack, so only the filter is pinned).
$aiArgs = null;
$aiFilter = static function () use (&$aiArgs) {
    $aiArgs = func_get_args();
    return false;
};
add_filter('wp_supports_ai', $aiFilter, 10, 9);
$say('wp_supports_ai', [defined('WP_AI_SUPPORT'), wp_supports_ai(), $aiArgs]);
remove_filter('wp_supports_ai', $aiFilter, 10);
$aiString = static fn () => 'yes';
add_filter('wp_supports_ai', $aiString);
$say('wp_supports_ai string', [wp_supports_ai(), apply_filters('wp_supports_ai', true)]);
remove_filter('wp_supports_ai', $aiString);
$say('wp_supports_ai plain', wp_supports_ai());

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
