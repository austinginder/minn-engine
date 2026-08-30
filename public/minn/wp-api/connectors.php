<?php
/**
 * Connectors: the registry of external services a site talks to, the key
 * sources, and the credentials helpers. The registry itself is
 * Minn\Runtime\Connectors behind WP_Connector_Registry; the defaults register
 * at init 15 and wp_connectors_init hands the registry to plugin code.
 */

use Minn\Runtime\Connectors;

function wp_get_connectors(): array
{
    return WP_Connector_Registry::get_instance()->get_all_registered();
}

function wp_get_connector(string $id): ?array
{
    return WP_Connector_Registry::get_instance()->get_registered($id);
}

function wp_is_connector_registered(string $id): bool
{
    return WP_Connector_Registry::get_instance()->is_registered($id);
}

function _wp_connectors_register_default_ai_providers(WP_Connector_Registry $registry): void
{
    $registry->store()->registerDefaults(static fn (string $file): bool => is_plugin_active($file));
}

function _wp_connectors_init(): void
{
    $registry = WP_Connector_Registry::get_instance();
    _wp_connectors_register_default_ai_providers($registry);
    do_action('wp_connectors_init', $registry);
}

/** A connector's key setting is registered only while its plugin is active, so the settings route refuses a key nothing would use. */
function _wp_register_default_connector_settings(): void
{
    foreach (wp_get_connectors() as $connector) {
        $auth = $connector['authentication'];
        if (($auth['method'] ?? '') !== 'api_key' || !is_string($auth['setting_name'] ?? null)) {
            continue;
        }
        if (!empty($connector['plugin']['is_active']) && !call_user_func($connector['plugin']['is_active'])) {
            continue;
        }
        register_setting('connectors', $auth['setting_name'], ['type' => 'string', 'show_in_rest' => true, 'default' => '', 'sanitize_callback' => 'sanitize_text_field']);
    }
}

function _wp_connectors_pass_default_keys_to_ai_client(): void
{
}

function _wp_connectors_mask_api_key(string $key): string
{
    return Connectors::mask($key);
}

function _wp_connectors_get_api_key_source(string $setting_name, string $env_var_name = '', string $constant_name = ''): string
{
    return Connectors::keySource($setting_name, $env_var_name, $constant_name, static fn (string $name): mixed => get_option($name, ''));
}

function _wp_connectors_is_ai_api_key_valid(string $key, string $provider_id): ?bool
{
    _doing_it_wrong(__FUNCTION__, sprintf('The provider "%s" is not registered in the AI client registry.', $provider_id), '7.0.0');
    return null;
}

function _wp_connectors_resolve_ai_provider_logo_url(string $path): ?string
{
    return null;
}

function wp_connectors_parse_application_password_credentials(string $value): array
{
    return Connectors::parseCredentials($value);
}

function wp_connectors_sanitize_application_password_credentials($value, string $option = ''): array
{
    return Connectors::sanitizeCredentials($value, static fn (string $text): string => sanitize_text_field($text));
}

function wp_connectors_get_application_password_credentials(array $auth): array
{
    return Connectors::credentials($auth, static fn (string $name): mixed => get_option($name, ''), static fn (string $text): string => sanitize_text_field($text));
}
