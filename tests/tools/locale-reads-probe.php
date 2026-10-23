<?php
/**
 * How get_locale reads the site's locale (probe locale-reads): the WPLANG
 * option through get_option's filters, kept in the $locale global
 * unfiltered and read from there after, a value already in the global
 * taking over, the locale filter on every ask, a filter on the option
 * changing the answer, and last (a constant stays for the process) the
 * WPLANG constant answering when the option names none. Writes nothing;
 * the global is put back. Same protocol as api-probe.php.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
global $locale;
$before = $locale;
$heard = [];
foreach (['pre_option_WPLANG', 'default_option_WPLANG', 'option_WPLANG', 'locale'] as $hook) {
    add_filter($hook, static function ($value) use (&$heard, $hook) {
        $heard[] = $hook;
        return $value;
    });
}
$locale = null;
$first = get_locale();
$say('read first', [$first, $heard, $GLOBALS['locale'] ?? '(unset)']);
$heard = [];
$say('read again', [get_locale(), $heard]);
$heard = [];
$locale = 'fr_FR';
$say('a value already in the global', [get_locale(), $heard]);
$heard = [];
unset($GLOBALS['locale']);
$say('the global unset', [get_locale(), $heard, $GLOBALS['locale'] ?? '(unset)']);
// Unsetting the global broke this file's binding to it.
global $locale;
$heard = [];
$mark = static fn ($value) => $value . '+marked';
add_filter('locale', $mark);
$locale = null;
$say('the locale filter changes the answer, not what is kept', [get_locale(), get_locale(), $GLOBALS['locale'] ?? '(unset)']);
remove_filter('locale', $mark);
$swap = static fn () => 'de_DE';
add_filter('pre_option_WPLANG', $swap, 20);
$locale = null;
$say('a filter on the option', [get_locale(), $GLOBALS['locale'] ?? '(unset)']);
remove_filter('pre_option_WPLANG', $swap, 20);
if (!defined('WPLANG')) {
    define('WPLANG', 'nl_NL');
}
$locale = null;
$constant = get_locale();
add_filter('pre_option_WPLANG', $swap, 20);
$locale = null;
$say('the WPLANG constant, then the option over it', [$constant, get_locale()]);
remove_filter('pre_option_WPLANG', $swap, 20);
$locale = $before;

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE), "\n";
