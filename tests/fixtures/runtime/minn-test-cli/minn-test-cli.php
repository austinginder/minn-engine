<?php
/**
 * Plugin Name: Minn CLI probe
 * Description: A WordPress plugin, unchanged, that registers a WP-CLI command the way plugins do. The cli suite switches it on and runs the command on both stacks.
 * Version: 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

if (defined('WP_CLI') && WP_CLI) {
    /**
     * Probes the WordPress a plugin command runs in.
     */
    class Minn_Test_CLI_Command
    {
        /**
         * Says hello from the site.
         *
         * [--name=<name>]
         * : Who to greet.
         */
        public function hello($args, $assoc_args)
        {
            WP_CLI::line('hello ' . ($assoc_args['name'] ?? 'world') . ' from ' . get_option('blogname'));
            WP_CLI::line('init fired: ' . (did_action('init') ? 'yes' : 'no'));
            WP_CLI::line('wp_loaded fired: ' . (did_action('wp_loaded') ? 'yes' : 'no'));
            WP_CLI::line('published posts: ' . wp_count_posts()->publish);
            WP_CLI::line('first post: ' . get_the_title(1));
            WP_CLI::success('done');
        }

        /**
         * Fails the way a plugin command fails.
         */
        public function fail($args, $assoc_args)
        {
            WP_CLI::error('probe failure');
        }
    }

    WP_CLI::add_command('zz-cli-probe', 'Minn_Test_CLI_Command');
}
