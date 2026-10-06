<?php

declare(strict_types=1);

namespace Minn\Runtime;

/**
 * Settings as register_setting keeps them (probe rest-settings): the
 * arguments a plugin passes, filtered through register_setting_args and
 * laid over the defaults (a string, in its group, unlabelled, undescribed,
 * unsanitized, not shown in REST); the sanitize callback put on
 * sanitize_option_{$name} with the value alone; a registered default
 * answering get_option through filter_default_option; register_setting
 * fired once kept. unregister_setting takes all of it back. Core's own
 * settings (the site's title, tagline, addresses, formats, reading and
 * discussion defaults) are registered as the REST server starts.
 */
final class RegisteredSettings
{
    private const KEY = 'registered_settings';

    private const ARRAY_ITEMS = 'When registering an "array" setting to show in the REST API, you must specify the schema for each array item in "show_in_rest.schema.items".';

    /** Core's settings: name => [group, type, label, description, show_in_rest, default when it has one]. */
    private const CORE = [
        'blogname' => ['general', 'string', 'Title', 'Site title.', ['name' => 'title']],
        'blogdescription' => ['general', 'string', 'Tagline', 'Site tagline.', ['name' => 'description']],
        'siteurl' => ['general', 'string', '', 'Site URL.', ['name' => 'url', 'schema' => ['format' => 'uri']]],
        'admin_email' => ['general', 'string', '', 'This address is used for admin purposes, like new user notification.', ['name' => 'email', 'schema' => ['format' => 'email']]],
        'timezone_string' => ['general', 'string', '', 'A city in the same timezone as you.', ['name' => 'timezone']],
        'date_format' => ['general', 'string', '', 'A date format for all date strings.', true],
        'time_format' => ['general', 'string', '', 'A time format for all time strings.', true],
        'start_of_week' => ['general', 'integer', '', 'A day number of the week that the week should start on.', true],
        'WPLANG' => ['general', 'string', '', 'WordPress locale code.', ['name' => 'language'], 'en_US'],
        'use_smilies' => ['writing', 'boolean', '', 'Convert emoticons like :-) and :-P to graphics on display.', true, true],
        'default_category' => ['writing', 'integer', '', 'Default post category.', true],
        'default_post_format' => ['writing', 'string', '', 'Default post format.', true],
        'posts_per_page' => ['reading', 'integer', 'Maximum posts per page', 'Blog pages show at most.', true, 10],
        'show_on_front' => ['reading', 'string', 'Show on front', 'What to show on the front page', true],
        'page_on_front' => ['reading', 'integer', 'Page on front', 'The ID of the page that should be displayed on the front page', true],
        'page_for_posts' => ['reading', 'integer', '', 'The ID of the page that should display the latest posts', true],
        'default_ping_status' => ['discussion', 'string', '', 'Allow link notifications from other blogs (pingbacks and trackbacks) on new articles.', ['schema' => ['enum' => ['open', 'closed']]]],
        'default_comment_status' => ['discussion', 'string', 'Allow comments on new posts', 'Allow people to submit comments on new posts.', ['schema' => ['enum' => ['open', 'closed']]]],
        'site_logo' => ['general', 'integer', 'Logo', 'Site logo.', ['name' => 'site_logo']],
        'site_icon' => ['general', 'integer', 'Icon', 'Site icon.', true],
    ];

    /** Every registered setting by option name, in registration order. @return array<string, array<string, mixed>> */
    public static function all(): array
    {
        return (array) Runtime::current()->get(self::KEY, []);
    }

    /** register_setting: $args an array, or (as it once was) the sanitize callback alone. */
    public static function register(string $group, string $name, mixed $args): void
    {
        $defaults = ['type' => 'string', 'group' => $group, 'label' => '', 'description' => '', 'sanitize_callback' => null, 'show_in_rest' => false];
        $args = is_array($args) ? $args : ['sanitize_callback' => $args];
        $args = (array) \apply_filters('register_setting_args', $args, $defaults, $group, $name);
        $args = array_merge($defaults, $args);
        $rest = $args['show_in_rest'];
        if ($rest && $args['type'] === 'array' && empty($rest['schema']['items'])) {
            \_doing_it_wrong('register_setting', self::ARRAY_ITEMS, '5.4.0');
        }
        $registered = self::all();
        $registered[$name] = $args;
        Runtime::current()->set(self::KEY, $registered);
        if (!empty($args['sanitize_callback'])) {
            \add_filter("sanitize_option_{$name}", $args['sanitize_callback']);
        }
        if (array_key_exists('default', $args)) {
            \add_filter("default_option_{$name}", 'filter_default_option', 10, 3);
        }
        \do_action('register_setting', $group, $name, $args);
    }

    /** unregister_setting: the setting, its sanitize callback and its default gone, unregister_setting fired. */
    public static function unregister(string $group, string $name, mixed $deprecated = ''): void
    {
        $registered = self::all();
        if (!isset($registered[$name])) {
            return;
        }
        if (!empty($deprecated)) {
            \remove_filter("sanitize_option_{$name}", $deprecated);
        }
        $args = $registered[$name];
        if (!empty($args['sanitize_callback'])) {
            \remove_filter("sanitize_option_{$name}", $args['sanitize_callback']);
        }
        if (array_key_exists('default', $args)) {
            \remove_filter("default_option_{$name}", 'filter_default_option', 10);
        }
        unset($registered[$name]);
        Runtime::current()->set(self::KEY, $registered);
        \do_action('unregister_setting', $group, $name);
    }

    /** filter_default_option: the registered default, unless the reader named its own. */
    public static function defaultOf(mixed $default, string $option, bool $passed): mixed
    {
        $registered = self::all();
        if ($passed || empty($registered[$option]) || !array_key_exists('default', $registered[$option])) {
            return $default;
        }
        return $registered[$option]['default'];
    }

    /** register_initial_settings: core's settings, each through register_setting. */
    public static function registerCore(): void
    {
        foreach (self::CORE as $name => $row) {
            [$group, $type, $label, $description, $rest] = $row;
            $args = ['type' => $type, 'label' => $label, 'description' => $description, 'show_in_rest' => $rest];
            if (array_key_exists(5, $row)) {
                $args['default'] = $row[5];
            }
            \register_setting($group, $name, $args);
        }
    }
}
