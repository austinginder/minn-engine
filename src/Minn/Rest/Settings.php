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

    public function __construct(private Site $site)
    {
    }

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

    /** Writes the registered keys in a body; unregistered keys are ignored. */
    public function store(array $body): void
    {
        foreach ($body as $key => $value) {
            if (!isset(self::REGISTRY[$key])) {
                continue;
            }
            [$option, $type] = self::REGISTRY[$key];
            $stored = match ($type) {
                'int' => (string) (int) $value,
                'bool' => $value ? '1' : '',
                'language' => $value === 'en_US' ? '' : (string) $value,
                'int_or_null' => $value === null ? '' : (string) (int) $value,
                default => (string) $value,
            };
            $this->site->setOption($option, $stored);
        }
    }
}
