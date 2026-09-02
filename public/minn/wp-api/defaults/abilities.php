<?php
/**
 * The abilities the reference registers in core, so a plain Minn site
 * answers /wp-abilities/v1 with the same catalogue: what an agent asks a
 * site about itself. The labels, descriptions and schemas were captured
 * from the reference; the callbacks are the engine's own.
 *
 * Each is read-only, so its run endpoint takes GET, and each is gated the
 * way the reference gates it.
 */

wp_register_ability_category('site', [
    'label' => 'Site',
    'description' => 'Abilities that retrieve or modify site information and settings.',
    ]);
wp_register_ability_category('user', [
    'label' => 'User',
    'description' => 'Abilities that retrieve or modify user information and settings.',
    ]);

wp_register_ability('core/get-site-info', [
    'label' => 'Get Site Information',
    'description' => 'Returns site information configured in WordPress. By default returns all fields, or optionally a filtered subset.',
    'category' => 'site',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
        'fields' => [
            'type' => 'array',
            'items' => [
            'type' => 'string',
            'enum' => [
                'name',
                'description',
                'url',
                'wpurl',
                'admin_email',
                'charset',
                'language',
                'version',
            ],
            ],
            'description' => 'Optional: Limit response to specific fields. If omitted, all fields are returned.',
        ],
        ],
        'additionalProperties' => false,
        'default' => [],
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
        'name' => [
            'type' => 'string',
            'title' => 'Site Title',
            'description' => 'The site title.',
        ],
        'description' => [
            'type' => 'string',
            'title' => 'Tagline',
            'description' => 'The site tagline.',
        ],
        'url' => [
            'type' => 'string',
            'title' => 'Site Address (URL)',
            'description' => 'The public URL where visitors access the site. May differ from the WordPress installation URL.',
        ],
        'wpurl' => [
            'type' => 'string',
            'title' => 'WordPress Address (URL)',
            'description' => 'The URL where WordPress core files are served. May differ from the public site URL.',
        ],
        'admin_email' => [
            'type' => 'string',
            'title' => 'Administration Email Address',
            'description' => 'The site administrator email address.',
        ],
        'charset' => [
            'type' => 'string',
            'title' => 'Site Charset',
            'description' => 'The site character encoding.',
        ],
        'language' => [
            'type' => 'string',
            'title' => 'Site Language',
            'description' => 'The site locale in dash form (e.g. en-US).',
        ],
        'version' => [
            'type' => 'string',
            'title' => 'WordPress Version',
            'description' => 'The WordPress core version running on this site.',
        ],
        ],
        'additionalProperties' => false,
    ],
    'meta' => [
        'annotations' => [
        'readonly' => true,
        'destructive' => false,
        'idempotent' => true,
        ],
        'public' => true,
        'show_in_rest' => true,
    ],
    'execute_callback' => static fn (mixed $input): mixed => _minn_ability_get_site_info((array) ($input ?? [])),
    'permission_callback' => static fn (): bool => _minn_ability_may_get_site_info(),
]);

wp_register_ability('core/get-user-info', [
    'label' => 'Get User Information',
    'description' => 'Returns profile details for the current authenticated user to support personalization, auditing, and access-aware behavior. By default returns all fields, or optionally a filtered subset.',
    'category' => 'user',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
        'fields' => [
            'type' => 'array',
            'items' => [
            'type' => 'string',
            'enum' => [
                'id',
                'display_name',
                'user_nicename',
                'user_login',
                'roles',
                'locale',
                'first_name',
                'last_name',
                'nickname',
                'description',
                'user_url',
            ],
            ],
            'description' => 'Optional: Limit response to specific fields. If omitted, all fields are returned.',
        ],
        ],
        'additionalProperties' => false,
        'default' => [],
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
        'id' => [
            'type' => 'integer',
            'title' => 'User ID',
            'description' => 'Unique identifier for the user.',
        ],
        'display_name' => [
            'type' => 'string',
            'title' => 'Display Name',
            'description' => 'Public-facing name selected by the user.',
        ],
        'user_nicename' => [
            'type' => 'string',
            'title' => 'User Nicename',
            'description' => 'URL-friendly slug for the user. Defaults to the username.',
        ],
        'user_login' => [
            'type' => 'string',
            'title' => 'Username',
            'description' => 'Login identifier for the user. Cannot be changed once set.',
        ],
        'roles' => [
            'type' => 'array',
            'title' => 'Roles',
            'description' => 'Roles assigned to the user, such as administrator, editor, author, contributor, or subscriber.',
            'items' => [
            'type' => 'string',
            ],
        ],
        'locale' => [
            'type' => 'string',
            'title' => 'Language',
            'description' => 'Locale code for the user, such as en_US.',
        ],
        'first_name' => [
            'type' => 'string',
            'title' => 'First Name',
            'description' => 'Given name.',
        ],
        'last_name' => [
            'type' => 'string',
            'title' => 'Last Name',
            'description' => 'Family name.',
        ],
        'nickname' => [
            'type' => 'string',
            'title' => 'Nickname',
            'description' => 'Informal name. Defaults to the username.',
        ],
        'description' => [
            'type' => 'string',
            'title' => 'Biographical Info',
            'description' => 'User-authored biography. May be empty.',
        ],
        'user_url' => [
            'type' => 'string',
            'title' => 'Website',
            'description' => 'Personal website URL.',
        ],
        ],
        'additionalProperties' => false,
    ],
    'meta' => [
        'annotations' => [
        'readonly' => true,
        'destructive' => false,
        'idempotent' => true,
        ],
        'public' => true,
        'show_in_rest' => true,
    ],
    'execute_callback' => static fn (mixed $input): mixed => _minn_ability_get_user_info((array) ($input ?? [])),
    'permission_callback' => static fn (): bool => _minn_ability_may_get_user_info(),
]);

wp_register_ability('core/get-environment-info', [
    'label' => 'Get Environment Info',
    'description' => 'Returns core details about the site\'s runtime context for diagnostics and compatibility (environment, PHP runtime, database server info, WordPress version). By default returns all fields, or optionally a filtered subset.',
    'category' => 'site',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
        'fields' => [
            'type' => 'array',
            'items' => [
            'type' => 'string',
            'enum' => [
                'environment',
                'php_version',
                'db_server_info',
                'wp_version',
            ],
            ],
            'description' => 'Optional: Limit response to specific fields. If omitted, all fields are returned.',
        ],
        ],
        'additionalProperties' => false,
        'default' => [],
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
        'environment' => [
            'type' => 'string',
            'title' => 'Environment Type',
            'description' => 'The site\'s runtime environment classification.',
            'enum' => [
            'production',
            'staging',
            'development',
            'local',
            ],
        ],
        'php_version' => [
            'type' => 'string',
            'title' => 'PHP Version',
            'description' => 'The PHP runtime version executing WordPress.',
        ],
        'db_server_info' => [
            'type' => 'string',
            'title' => 'Database Server Info',
            'description' => 'The database server vendor and version string reported by the driver.',
        ],
        'wp_version' => [
            'type' => 'string',
            'title' => 'WordPress Version',
            'description' => 'The WordPress core version running on this site.',
        ],
        ],
        'additionalProperties' => false,
    ],
    'meta' => [
        'annotations' => [
        'readonly' => true,
        'destructive' => false,
        'idempotent' => true,
        ],
        'public' => true,
        'show_in_rest' => true,
    ],
    'execute_callback' => static fn (mixed $input): mixed => _minn_ability_get_environment_info((array) ($input ?? [])),
    'permission_callback' => static fn (): bool => _minn_ability_may_get_environment_info(),
]);

/** The site facts core's get-site-info answers with, narrowed to the fields asked for. */
function _minn_ability_get_site_info(array $input): array
{
    $all = [
        'name' => get_bloginfo('name'),
        'description' => get_bloginfo('description'),
        'url' => get_bloginfo('url'),
        'wpurl' => get_bloginfo('wpurl'),
        'admin_email' => get_bloginfo('admin_email'),
        'charset' => get_bloginfo('charset'),
        'language' => get_bloginfo('language'),
        'version' => get_bloginfo('version'),
    ];
    return _minn_ability_fields($all, $input);
}

/** Reading the site's own settings is an administrator's business, as the reference gates it. */
function _minn_ability_may_get_site_info(): bool
{
    return current_user_can('manage_options');
}

/** The signed-in user's own profile. */
function _minn_ability_get_user_info(array $input): array
{
    $user = wp_get_current_user();
    $all = [
        'id' => (int) $user->ID,
        'display_name' => (string) $user->display_name,
        'user_nicename' => (string) $user->user_nicename,
        'user_login' => (string) $user->user_login,
        'roles' => array_values((array) $user->roles),
        'locale' => get_user_locale($user->ID),
        'first_name' => (string) get_user_meta($user->ID, 'first_name', true),
        'last_name' => (string) get_user_meta($user->ID, 'last_name', true),
        'nickname' => (string) get_user_meta($user->ID, 'nickname', true),
        'description' => (string) get_user_meta($user->ID, 'description', true),
        'user_url' => (string) $user->user_url,
    ];
    return _minn_ability_fields($all, $input);
}

/** Anyone signed in may read their own profile. */
function _minn_ability_may_get_user_info(): bool
{
    return is_user_logged_in();
}

/** What the site runs on, for diagnostics. */
function _minn_ability_get_environment_info(array $input): array
{
    global $wpdb;
    $all = [
        'environment' => wp_get_environment_type(),
        'php_version' => PHP_VERSION,
        'db_server_info' => is_object($wpdb) ? (string) $wpdb->db_server_info() : '',
        'wp_version' => get_bloginfo('version'),
    ];
    return _minn_ability_fields($all, $input);
}

/** The runtime's details are an administrator's business. */
function _minn_ability_may_get_environment_info(): bool
{
    return current_user_can('manage_options');
}

/**
 * @internal the shared "fields" narrowing every core ability takes: an
 * empty or absent list means every field.
 */
function _minn_ability_fields(array $all, array $input): array
{
    $fields = array_values(array_filter((array) ($input['fields'] ?? []), 'is_string'));
    if ($fields === []) {
        return $all;
    }
    return array_intersect_key($all, array_flip($fields));
}
