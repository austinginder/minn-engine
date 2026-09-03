<?php

declare(strict_types=1);

namespace Minn\Rest;

use Minn\Content\Site;

/**
 * The registered settings the Settings views read and write, mapped to
 * the options WordPress stores. Registration order is the payload order.
 */
final readonly class Settings
{
    /** setting key => [option name, type] */
    private const REGISTRY = [
        'blog_public' => ['blog_public', 'int'],
        'minn_admin_maintenance' => ['minn_admin_maintenance', 'bool'],
        'users_can_register' => ['users_can_register', 'int'],
        'default_role' => ['default_role', 'string'],
        'comment_moderation' => ['comment_moderation', 'int'],
        'comment_registration' => ['comment_registration', 'int'],
        'show_avatars' => ['show_avatars', 'int'],
        'title' => ['blogname', 'string'],
        'description' => ['blogdescription', 'string'],
        'url' => ['siteurl', 'string'],
        'email' => ['admin_email', 'string'],
        'timezone' => ['timezone_string', 'string'],
        'date_format' => ['date_format', 'string'],
        'time_format' => ['time_format', 'string'],
        'start_of_week' => ['start_of_week', 'int'],
        'language' => ['WPLANG', 'language'],
        'use_smilies' => ['use_smilies', 'bool'],
        'default_category' => ['default_category', 'int'],
        'default_post_format' => ['default_post_format', 'string'],
        'posts_per_page' => ['posts_per_page', 'int'],
        'show_on_front' => ['show_on_front', 'string'],
        'page_on_front' => ['page_on_front', 'int'],
        'page_for_posts' => ['page_for_posts', 'int'],
        'default_ping_status' => ['default_ping_status', 'string'],
        'default_comment_status' => ['default_comment_status', 'string'],
        'site_logo' => ['site_logo', 'int_or_null'],
        'site_icon' => ['site_icon', 'int'],
    ];

    /** The body of a write, every registered setting by its key, as the reference describes it. */
    public const SCHEMA = [
        'blog_public' => ['title' => '', 'description' => '', 'type' => 'integer', 'required' => false],
        'minn_admin_maintenance' => ['title' => '', 'description' => '', 'type' => 'boolean', 'required' => false],
        'users_can_register' => ['title' => '', 'description' => '', 'type' => 'integer', 'required' => false],
        'default_role' => ['title' => '', 'description' => '', 'type' => 'string', 'required' => false],
        'comment_moderation' => ['title' => '', 'description' => '', 'type' => 'integer', 'required' => false],
        'comment_registration' => ['title' => '', 'description' => '', 'type' => 'integer', 'required' => false],
        'show_avatars' => ['title' => '', 'description' => '', 'type' => 'integer', 'required' => false],
        'title' => ['title' => 'Title', 'description' => 'Site title.', 'type' => 'string', 'required' => false],
        'description' => ['title' => 'Tagline', 'description' => 'Site tagline.', 'type' => 'string', 'required' => false],
        'url' => ['title' => '', 'description' => 'Site URL.', 'type' => 'string', 'format' => 'uri', 'required' => false],
        'email' => ['title' => '', 'description' => 'This address is used for admin purposes, like new user notification.', 'type' => 'string', 'format' => 'email', 'required' => false],
        'timezone' => ['title' => '', 'description' => 'A city in the same timezone as you.', 'type' => 'string', 'required' => false],
        'date_format' => ['title' => '', 'description' => 'A date format for all date strings.', 'type' => 'string', 'required' => false],
        'time_format' => ['title' => '', 'description' => 'A time format for all time strings.', 'type' => 'string', 'required' => false],
        'start_of_week' => ['title' => '', 'description' => 'A day number of the week that the week should start on.', 'type' => 'integer', 'required' => false],
        'language' => ['title' => '', 'description' => 'WordPress locale code.', 'type' => 'string', 'required' => false],
        'use_smilies' => ['title' => '', 'description' => 'Convert emoticons like :-) and :-P to graphics on display.', 'type' => 'boolean', 'required' => false],
        'default_category' => ['title' => '', 'description' => 'Default post category.', 'type' => 'integer', 'required' => false],
        'default_post_format' => ['title' => '', 'description' => 'Default post format.', 'type' => 'string', 'required' => false],
        'posts_per_page' => ['title' => 'Maximum posts per page', 'description' => 'Blog pages show at most.', 'type' => 'integer', 'required' => false],
        'show_on_front' => ['title' => 'Show on front', 'description' => 'What to show on the front page', 'type' => 'string', 'required' => false],
        'page_on_front' => ['title' => 'Page on front', 'description' => 'The ID of the page that should be displayed on the front page', 'type' => 'integer', 'required' => false],
        'page_for_posts' => ['title' => '', 'description' => 'The ID of the page that should display the latest posts', 'type' => 'integer', 'required' => false],
        'default_ping_status' => ['title' => '', 'description' => 'Allow link notifications from other blogs (pingbacks and trackbacks) on new articles.', 'type' => 'string', 'enum' => ['open', 'closed'], 'required' => false],
        'default_comment_status' => ['title' => 'Allow comments on new posts', 'description' => 'Allow people to submit comments on new posts.', 'type' => 'string', 'enum' => ['open', 'closed'], 'required' => false],
        'site_logo' => ['title' => 'Logo', 'description' => 'Site logo.', 'type' => 'integer', 'required' => false],
        'site_icon' => ['title' => 'Icon', 'description' => 'Site icon.', 'type' => 'integer', 'required' => false],
    ];

    public function __construct(private Site $site)
    {
    }

    /** Every registered setting with its current value. */
    public function payload(): array
    {
        $out = [];
        foreach (self::REGISTRY as $key => [$option, $type]) {
            $raw = $this->site->option($option);
            $out[$key] = match ($type) {
                'int' => (int) ($raw ?? 0),
                // A missing option is the registered default (false). A STORED
                // empty string reads back as null: the reference stores boolean
                // false as '' and then fails to re-type it. Kept.
                'bool' => $raw === null ? false : ($raw === '' ? null : $raw !== '0'),
                'language' => ($raw === null || $raw === '') ? 'en_US' : $raw,
                'int_or_null' => ($raw === null || $raw === '') ? null : (int) $raw,
                default => (string) ($raw ?? ''),
            };
        }
        return $out;
    }

    /** Writes the registered keys in a body, already validated against SCHEMA; unregistered keys are ignored. */
    public function store(array $body): void
    {
        foreach ($body as $key => $value) {
            if (!isset(self::REGISTRY[$key])) {
                continue;
            }
            [$option, $type] = self::REGISTRY[$key];
            $stored = match ($type) {
                'int' => (string) (int) $value,
                'bool' => SchemaValues::toBoolean($value) ? '1' : '',
                'language' => $value === 'en_US' ? '' : (string) $value,
                'int_or_null' => $value === null ? '' : (string) (int) $value,
                default => (string) $value,
            };
            $this->site->setOption($option, $stored);
        }
    }
}
