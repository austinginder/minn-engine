<?php

declare(strict_types=1);

namespace Minn\Runtime;

use Closure;

/**
 * The connectors registry: the external services a site talks to (AI
 * providers, spam filters, cloud services) and how each authenticates.
 * Rows are normalised on the way in, the way the reference keeps them;
 * the facade class hands them back to plugin code.
 */
final class Connectors
{
    private const Methods = ['api_key', 'application_password', 'none'];
    private const AuthenticationKeys = ['method', 'credentials_url', 'setting_name', 'constant_name', 'env_var_name'];
    /** The reference stops adding bullets after sixteen, whatever the key's length. */
    private const MaskCap = 16;

    /** @var array<string, array<string, mixed>> */
    private array $rows = [];

    public function register(string $id, array $args): ?Refusal
    {
        if ($id === '' || preg_match('/^[a-z0-9_-]+$/', $id) !== 1) {
            return new Refusal('connector_id', 'Connector ID must contain only lowercase alphanumeric characters, hyphens, and underscores.');
        }
        if (isset($this->rows[$id])) {
            return new Refusal('connector_registered', sprintf('Connector "%s" is already registered.', $id));
        }
        if (!is_string($args['type'] ?? null) || $args['type'] === '') {
            return new Refusal('connector_type', sprintf('Connector "%s" requires a non-empty "type" string.', $id));
        }
        if (!is_string($args['name'] ?? null) || $args['name'] === '') {
            return new Refusal('connector_name', sprintf('Connector "%s" requires a non-empty "name" string.', $id));
        }
        if (!is_array($args['authentication'] ?? null)) {
            return new Refusal('connector_authentication', sprintf('Connector "%s" requires an "authentication" array.', $id));
        }
        $authentication = $args['authentication'];
        if (!in_array($authentication['method'] ?? null, self::Methods, true)) {
            return new Refusal('connector_method', sprintf('Connector "%s" authentication method must be "api_key", "application_password", or "none".', $id));
        }
        $plugin = is_array($args['plugin'] ?? null) ? $args['plugin'] : [];
        if (isset($plugin['is_active']) && !is_callable($plugin['is_active'])) {
            return new Refusal('connector_plugin', sprintf('Connector "%s" plugin is_active must be callable.', $id));
        }
        $this->rows[$id] = $this->normalise($id, $args, $authentication, $plugin);
        return null;
    }

    /** @return array<string, mixed> */
    private function normalise(string $id, array $args, array $authentication, array $plugin): array
    {
        if ($authentication['method'] === 'api_key' && !is_string($authentication['setting_name'] ?? null)) {
            $authentication['setting_name'] = sprintf('connectors_%s_%s_api_key', $args['type'], $id);
        }
        // Known keys only, in the reference's order whatever order the caller used.
        $known = array_intersect_key($authentication, array_flip(self::AuthenticationKeys));
        $row = [
            'name' => $args['name'],
            'description' => is_string($args['description'] ?? null) ? $args['description'] : '',
            'type' => $args['type'],
            'authentication' => array_replace(array_intersect_key(array_flip(self::AuthenticationKeys), $known), $known),
        ];
        if (is_string($args['logo_url'] ?? null)) {
            $row['logo_url'] = $args['logo_url'];
        }
        $row['plugin'] = [];
        if (is_string($plugin['file'] ?? null)) {
            $row['plugin']['file'] = $plugin['file'];
        }
        $row['plugin']['is_active'] = $plugin['is_active'] ?? '__return_true';
        return $row;
    }

    /** @return array<string, mixed>|null the row that was registered, null when there was none */
    public function unregister(string $id): ?array
    {
        $row = $this->rows[$id] ?? null;
        unset($this->rows[$id]);
        return $row;
    }

    /** @return array<string, array<string, mixed>> */
    public function all(): array
    {
        return $this->rows;
    }

    public function get(string $id): ?array
    {
        return $this->rows[$id] ?? null;
    }

    public function has(string $id): bool
    {
        return isset($this->rows[$id]);
    }

    /**
     * The reference's three AI providers and Akismet. Activation is
     * checked through the caller's closure, so the plugin list is read
     * when asked.
     * @param Closure(string): bool $pluginActive
     */
    public function registerDefaults(Closure $pluginActive): void
    {
        $provider = static fn (string $name, string $description, string $url, string $id, string $slug, string $constant): array => [
            'name' => $name,
            'description' => $description,
            'type' => 'ai_provider',
            'authentication' => ['method' => 'api_key', 'credentials_url' => $url, 'setting_name' => "connectors_ai_{$id}_api_key", 'constant_name' => $constant, 'env_var_name' => $constant],
            'plugin' => ['file' => "{$slug}/plugin.php", 'is_active' => static fn (): bool => $pluginActive("{$slug}/plugin.php")],
        ];
        $this->register('anthropic', $provider('Anthropic', 'Text generation with Claude.', 'https://platform.claude.com/settings/keys', 'anthropic', 'ai-provider-for-anthropic', 'ANTHROPIC_API_KEY'));
        $this->register('google', $provider('Google', 'Text and image generation with Gemini and Imagen.', 'https://aistudio.google.com/api-keys', 'google', 'ai-provider-for-google', 'GOOGLE_API_KEY'));
        $this->register('openai', $provider('OpenAI', 'Text and image generation with GPT and Dall-E.', 'https://platform.openai.com/api-keys', 'openai', 'ai-provider-for-openai', 'OPENAI_API_KEY'));
        $this->register('akismet', [
            'name' => 'Akismet Anti-spam',
            'description' => 'Protect your site from spam.',
            'type' => 'spam_filtering',
            'authentication' => ['method' => 'api_key', 'credentials_url' => 'https://akismet.com/get/', 'setting_name' => 'wordpress_api_key', 'constant_name' => 'WPCOM_API_KEY'],
            'plugin' => ['file' => 'akismet/akismet.php', 'is_active' => static fn (): bool => $pluginActive('akismet/akismet.php')],
        ]);
    }

    /** Keys of four characters or fewer are shown whole; longer ones keep their last four behind at most sixteen bullets. */
    public static function mask(string $key): string
    {
        if (strlen($key) <= 4) {
            return $key;
        }
        return str_repeat('•', min(self::MaskCap, strlen($key) - 4)) . substr($key, -4);
    }

    /**
     * Where a key comes from, in the reference's precedence: the environment,
     * then a constant, then the stored option; empty values do not count.
     * @param Closure(string): mixed $option
     */
    public static function keySource(string $setting, string $envVar, string $constant, Closure $option): string
    {
        if ($envVar !== '' && (string) getenv($envVar) !== '') {
            return 'env';
        }
        if ($constant !== '' && defined($constant) && is_string(constant($constant)) && constant($constant) !== '') {
            return 'constant';
        }
        if ($setting !== '' && (string) $option($setting) !== '') {
            return 'database';
        }
        return 'none';
    }

    /** "user:password" split at the first colon, both halves trimmed; anything else is empty credentials. */
    public static function parseCredentials(string $value): array
    {
        $colon = strpos($value, ':');
        if ($colon === false) {
            return ['username' => '', 'password' => ''];
        }
        $username = trim(substr($value, 0, $colon));
        $password = trim(substr($value, $colon + 1));
        if ($username === '' || $password === '') {
            return ['username' => '', 'password' => ''];
        }
        return ['username' => $username, 'password' => $password];
    }

    /**
     * Stored credentials are an array of two text fields; a string, even a
     * "user:password" one, is not accepted from storage.
     * @param Closure(string): string $clean
     */
    public static function sanitizeCredentials(mixed $value, Closure $clean): array
    {
        if (!is_array($value)) {
            return ['username' => '', 'password' => ''];
        }
        return [
            'username' => is_string($value['username'] ?? null) ? $clean($value['username']) : '',
            'password' => is_string($value['password'] ?? null) ? $clean($value['password']) : '',
        ];
    }

    /**
     * The credentials a connector authenticates with, from the same three
     * sources as a key, plus where they came from.
     * @param Closure(string): mixed $option
     * @param Closure(string): string $clean
     */
    public static function credentials(array $auth, Closure $option, Closure $clean): array
    {
        $envVar = is_string($auth['env_var_name'] ?? null) ? $auth['env_var_name'] : '';
        $constant = is_string($auth['constant_name'] ?? null) ? $auth['constant_name'] : '';
        $setting = is_string($auth['setting_name'] ?? null) ? $auth['setting_name'] : '';
        if ($envVar !== '' && (string) getenv($envVar) !== '') {
            return self::parseCredentials((string) getenv($envVar)) + ['source' => 'env'];
        }
        if ($constant !== '' && defined($constant) && is_string(constant($constant)) && constant($constant) !== '') {
            return self::parseCredentials(constant($constant)) + ['source' => 'constant'];
        }
        $stored = $setting === '' ? null : $option($setting);
        if (is_array($stored)) {
            $credentials = self::sanitizeCredentials($stored, $clean);
            if ($credentials['username'] !== '' || $credentials['password'] !== '') {
                return $credentials + ['source' => 'database'];
            }
        }
        return ['username' => '', 'password' => '', 'source' => 'none'];
    }
}
