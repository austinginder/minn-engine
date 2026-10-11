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

/**
 * A connector registry of its own, made the instance (only while init runs;
 * otherwise refused, and the instance kept), given the default AI providers,
 * then handed to wp_connectors_init (probe boot-defaults).
 */
function _wp_connectors_init(): void
{
    $registry = new WP_Connector_Registry();
    WP_Connector_Registry::set_instance($registry);
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

/**
 * Connector credentials kept out of the settings answer (on
 * rest_post_dispatch, the /wp/v2/settings route only): each connector's
 * stored key is masked, and an application password's password field
 * (sixteen bullets; only in the array form, a string is left alone). On POST or PUT, an AI provider's key that does
 * not validate is stored empty and answered empty instead.
 */
function _wp_connectors_rest_settings_dispatch($response, $server, $request)
{
    if (!$response instanceof WP_REST_Response || $request->get_route() !== '/wp/v2/settings' || !is_array($data = $response->get_data())) {
        return $response;
    }
    $writing = in_array($request->get_method(), ['POST', 'PUT'], true);
    foreach (wp_get_connectors() as $id => $connector) {
        $setting = $connector['authentication']['setting_name'] ?? '';
        if ($setting !== '' && isset($data[$setting])) {
            $data[$setting] = _minn_connector_answer($id, $connector, $setting, $data[$setting], $writing);
        }
    }
    $response->set_data($data);
    return $response;
}

/** @internal one connector setting as the settings answer shows it */
function _minn_connector_answer(string $id, array $connector, string $setting, $value, bool $writing)
{
    if (($connector['authentication']['method'] ?? '') === 'application_password') {
        if (is_array($value) && isset($value['password'])) {
            $value['password'] = str_repeat('•', 16);
        }
        return $value;
    }
    if (!is_string($value) || $value === '') {
        return $value;
    }
    if ($writing && ($connector['type'] ?? '') === 'ai_provider' && !_wp_connectors_is_ai_api_key_valid($value, $id)) {
        update_option($setting, '');
        return '';
    }
    return _wp_connectors_mask_api_key($value);
}

/**
 * The connectors the Connectors options page is given (probe
 * avif-connectors-helpers), by id: each one's name, description, logo,
 * type, how it authenticates (its key's setting, where to get one, where
 * the key comes from, whether it is connected) and its plugin (installed,
 * active). A key connects a spam filter; an AI provider also needs its
 * plugin active. Whether file changes are refused comes along.
 */
function _wp_connectors_get_connector_script_module_data(array $data): array
{
    $connectors = [];
    foreach (wp_get_connectors() as $id => $connector) {
        $auth = (array) ($connector['authentication'] ?? []);
        $file = (string) ($connector['plugin']['file'] ?? '');
        $active = $file !== '' && is_plugin_active($file);
        $authentication = ['method' => $auth['method'] ?? ''];
        if (($auth['method'] ?? '') === 'api_key') {
            $source = _wp_connectors_get_api_key_source((string) ($auth['setting_name'] ?? ''), (string) ($auth['env_var_name'] ?? ''), (string) ($auth['constant_name'] ?? ''));
            $authentication += ['settingName' => $auth['setting_name'] ?? '', 'credentialsUrl' => $auth['credentials_url'] ?? null, 'keySource' => $source, 'isConnected' => $source !== 'none' && (($connector['type'] ?? '') !== 'ai_provider' || $active)];
        }
        $connectors[$id] = [
            'name' => $connector['name'] ?? '',
            'description' => $connector['description'] ?? '',
            'logoUrl' => $connector['logo_url'] ?? null,
            'type' => $connector['type'] ?? '',
            'authentication' => $authentication,
            'plugin' => ['file' => $file, 'isInstalled' => $file !== '' && file_exists(WP_PLUGIN_DIR . '/' . $file), 'isActivated' => $active],
        ];
    }
    ksort($connectors);
    $data['connectors'] = $connectors;
    // Whether plugins can be installed from the page.
    $data['isFileModDisabled'] = !wp_is_file_mod_allowed('install_plugins');
    return $data;
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

/** A site that defines WP_AI_SUPPORT as false has opted out, and no filter can opt it back in. */
function wp_supports_ai()
{
    if (defined('WP_AI_SUPPORT') && !WP_AI_SUPPORT) {
        return false;
    }
    return (bool) apply_filters('wp_supports_ai', true);
}
