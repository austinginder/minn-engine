<?php
/**
 * Plugin Name: Minn Test Sweep Fails
 * Description: Fixture for tests/security-sweep.test.php, staged in the test site's plugins while the suite runs. Its init fails on a request that carries ?minn_test_sweep_fail, an X-Minn-Test-Sweep-Fail header, or a path holding zz-sweep-fail, and on every request while the minn_test_sweep_fail_always option is set.
 */
add_action('init', static function (): void {
    $chosen = isset($_GET['minn_test_sweep_fail']) || isset($_SERVER['HTTP_X_MINN_TEST_SWEEP_FAIL']) || str_contains((string) ($_SERVER['REQUEST_URI'] ?? ''), 'zz-sweep-fail');
    if ($chosen || get_option('minn_test_sweep_fail_always')) {
        throw new RuntimeException('Minn Test Sweep Fails failed on purpose.');
    }
});
