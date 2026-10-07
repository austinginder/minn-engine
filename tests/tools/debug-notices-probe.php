<?php
/**
 * The notices WordPress raises for code used wrongly or past its time, as
 * the reference raises them under WP_DEBUG (probe debug-notices): each of
 * _deprecated_function, _argument, _hook, _file, _class, _constructor,
 * _doing_it_wrong and wp_trigger_error, with and without their optional
 * parts; the action each fires and the filter each asks before raising
 * (doing_it_wrong_trigger_error and the rest), a plugin's false silencing
 * it; the deprecated hook runners, with and without callbacks; and what
 * wp_trigger_error does with markup and each error level. Notices are
 * caught by an error handler, never printed. Same protocol as api-probe.php.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$raised = [];
$heard = [];
add_action('all', static function (string $hook, ...$args) use (&$heard): void {
    if (preg_match('/^(deprecated_[a-z]+_run|deprecated_file_included|doing_it_wrong_run|wp_trigger_error_run|deprecated_[a-z]+_trigger_error|doing_it_wrong_trigger_error)$/', $hook)) {
        $heard[] = [$hook, ...$args];
    }
});
$call = static function (string $label, callable $fn) use ($say, &$raised, &$heard): void {
    $raised = [];
    $heard = [];
    set_error_handler(static function (int $level, string $message) use (&$raised): bool {
        $raised[] = [$level, $message];
        return true;
    });
    try {
        $result = $fn();
        $thrown = null;
    } catch (Throwable $e) {
        $result = null;
        $thrown = [get_class($e), $e->getMessage()];
    } finally {
        restore_error_handler();
    }
    $say($label, array_filter(['raised' => $raised, 'heard' => $heard, 'returned' => $result, 'thrown' => $thrown], static fn ($v) => $v !== null && $v !== []));
};

$call('a deprecated function with its replacement', static fn () => _deprecated_function('zz_old', '1.2.3', 'zz_new()'));
$call('a deprecated function without one', static fn () => _deprecated_function('zz_old', '1.2.3'));
$call('a deprecated argument with a message', static fn () => _deprecated_argument('zz_fn', '2.0', 'Pass $b instead.'));
$call('a deprecated argument without one', static fn () => _deprecated_argument('zz_fn', '2.0'));
$call('a deprecated hook with its replacement and a message', static fn () => _deprecated_hook('zz_hook', '3.0', 'zz_new_hook', 'It moved.'));
$call('a deprecated hook bare', static fn () => _deprecated_hook('zz_hook', '3.0'));
$call('a deprecated file with its replacement and a message', static fn () => _deprecated_file('zz-old.php', '4.0', 'zz-new.php', 'Load the new one.'));
$call('a deprecated file bare', static fn () => _deprecated_file('zz-old.php', '4.0'));
$call('a deprecated hook with only a message', static fn () => _deprecated_hook('zz_hook', '3.0', '', 'It went away.'));
$call('a deprecated file with only a message', static fn () => _deprecated_file('zz-old.php', '4.0', '', 'Nothing replaces it.'));
$call('a deprecated class with its replacement', static fn () => _deprecated_class('Zz_Old', '5.0', 'Zz_New'));
$call('a deprecated class without one', static fn () => _deprecated_class('Zz_Old', '5.0'));
$call('a deprecated constructor with its parent', static fn () => _deprecated_constructor('Zz_Widget', '4.3.0', 'Zz_Parent'));
$call('a deprecated constructor without one', static fn () => _deprecated_constructor('Zz_Widget', '4.3.0'));
$call('doing it wrong with a version', static fn () => _doing_it_wrong('zz_fn', 'Call it after <code>init</code>.', '6.1.0'));
$call('doing it wrong without one', static fn () => _doing_it_wrong('Zz_Class::method', 'Not like that.', ''));
$call('a triggered error', static fn () => wp_trigger_error('zz_fn', 'Something <strong>odd</strong> <script>x</script> <a href="https://zz.example/" onclick="y">here</a>.'));
$call('a triggered error in every tag', static fn () => wp_trigger_error('zz_fn', '<a href="javascript:alert(1)" title="t" target="_blank" rel="noopener" class="c" id="i">a</a> <br> <br /> <em>em</em> <i>i</i> <b>b</b> <p>p</p> <span>s</span> <code>c</code> <abbr title="t">ab</abbr> <ul><li>li</li></ul> <img src="x"> &amp; &lt; "quoted" \'single\''));
$call('a triggered error with other protocols', static fn () => wp_trigger_error('zz_fn', '<a href="mailto:zz@zz.example">m</a> <a href="ftp://zz.example/">f</a> <a href="/zz/relative">r</a> <a href="tel:123">t</a>'));
$call('a triggered error from a function named in markup', static fn () => wp_trigger_error('<em>zz</em><script>', 'Plain.'));
$call('a triggered fatal with markup', static fn () => wp_trigger_error('zz_fn', 'A <strong>fatal</strong> <script>x</script>.', E_USER_ERROR));
$call('a triggered error with no function at E_USER_ERROR', static fn () => wp_trigger_error('', 'Bare fatal.', E_USER_ERROR));
$call('a triggered error with no function', static fn () => wp_trigger_error('', 'No function named.'));
$call('a triggered warning', static fn () => wp_trigger_error('zz_fn', 'A warning.', E_USER_WARNING));
$call('a triggered deprecation', static fn () => wp_trigger_error('zz_fn', 'A deprecation.', E_USER_DEPRECATED));
$call('a triggered error at E_USER_ERROR', static fn () => wp_trigger_error('zz_fn', 'A fatal.', E_USER_ERROR));
$call('a triggered error at an unknown level', static fn () => wp_trigger_error('zz_fn', 'Odd level.', E_WARNING));

foreach (['deprecated_function_trigger_error', 'deprecated_argument_trigger_error', 'deprecated_hook_trigger_error', 'deprecated_file_trigger_error', 'deprecated_class_trigger_error', 'deprecated_constructor_trigger_error', 'doing_it_wrong_trigger_error'] as $filter) {
    add_filter($filter, '__return_false');
}
$call('a deprecated function a plugin silences', static fn () => _deprecated_function('zz_old', '1.2.3', 'zz_new()'));
$call('a deprecated hook a plugin silences', static fn () => _deprecated_hook('zz_hook', '3.0'));
$call('doing it wrong a plugin silences', static fn () => _doing_it_wrong('zz_fn', 'Not like that.', '6.1.0'));
$call('a triggered error after the silencing', static fn () => wp_trigger_error('zz_fn', 'Still raised.'));
foreach (['deprecated_function_trigger_error', 'deprecated_argument_trigger_error', 'deprecated_hook_trigger_error', 'deprecated_file_trigger_error', 'deprecated_class_trigger_error', 'deprecated_constructor_trigger_error', 'doing_it_wrong_trigger_error'] as $filter) {
    remove_filter($filter, '__return_false');
}


$call('a REST controller that leaves register_routes alone', static fn () => (new class extends WP_REST_Controller {})->register_routes());
$call('a REST controller that leaves get_items alone', static function () {
    $answer = (new class extends WP_REST_Controller {})->get_items(new WP_REST_Request('GET', '/zz'));
    return is_wp_error($answer) ? [$answer->get_error_code(), $answer->get_error_message(), $answer->get_error_data()] : $answer;
});
$call('a deprecated filter nobody hooks', static fn () => apply_filters_deprecated('zz_old_filter', ['value'], '2.5', 'zz_new_filter', 'Use the new one.'));
add_filter('zz_old_filter', static fn ($v) => $v . '+hooked');
$call('a deprecated filter a plugin hooks', static fn () => apply_filters_deprecated('zz_old_filter', ['value'], '2.5', 'zz_new_filter', 'Use the new one.'));
$call('a deprecated action nobody hooks', static fn () => do_action_deprecated('zz_old_action', ['a'], '2.5'));
add_action('zz_old_action', static function (): void {});
$call('a deprecated action a plugin hooks', static fn () => do_action_deprecated('zz_old_action', ['a'], '2.5'));
echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
