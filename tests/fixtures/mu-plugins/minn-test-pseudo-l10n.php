<?php
/**
 * Fixture mu-plugin for tests/front-strings.test.php, staged in both stacks'
 * mu-plugin folders while the suite runs: on a request that carries the
 * minn_test_pseudo cookie, every translated string comes back marked
 * «like this», so a string a page prints without asking for its translation
 * stands out against the reference's.
 */
if (!isset($_COOKIE['minn_test_pseudo'])) {
    return;
}
$minn_test_mark = static fn ($translation) => is_string($translation) && $translation !== '' ? '«' . $translation . '»' : $translation;
add_filter('gettext', $minn_test_mark, 999);
add_filter('gettext_with_context', $minn_test_mark, 999);
add_filter('ngettext', $minn_test_mark, 999);
add_filter('ngettext_with_context', $minn_test_mark, 999);
