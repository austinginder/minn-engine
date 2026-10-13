<?php
/**
 * Plugin Name: Minn Test Sweep Fails
 * Description: Fixture for tests/security-sweep.test.php, staged in the test site's plugins while the suite runs. Its init fails on a request that carries ?minn_test_sweep_fail, and on every request while the minn_test_sweep_fail_always option is set.
 */
add_action('init', static function (): void {
    if (isset($_GET['minn_test_sweep_fail']) || get_option('minn_test_sweep_fail_always')) {
        throw new RuntimeException('Minn Test Sweep Fails failed on purpose.');
    }
});
