<?php
/**
 * The AI client: the SDK pieces and the WordPress adapters in wp-api/ai-client/,
 * loaded the first time anything names one.
 */

spl_autoload_register(static function (string $class): void {
    $class = ltrim($class, '\\');
    if (str_starts_with($class, 'WordPress\\AiClient') || str_starts_with($class, 'WP_AI_Client_')) {
        _minn_load_ai_client();
    }
});

/** @internal defines the AI client's classes, once */
function _minn_load_ai_client(): void
{
    static $loaded = false;
    if ($loaded) {
        return;
    }
    $loaded = true;
    foreach (['Psr', 'Psr7', 'Sdk', 'Dto', 'Builder', 'Adapters', 'Prompt'] as $file) {
        require_once __DIR__ . '/ai-client/' . $file . '.php';
    }
}

/** A prompt for the site's AI models (probe ai-client-prompt): a builder over the SDK's default provider registry. */
function wp_ai_client_prompt($prompt = null)
{
    return new WP_AI_Client_Prompt_Builder(WordPress\AiClient\AiClient::defaultRegistry(), $prompt);
}
