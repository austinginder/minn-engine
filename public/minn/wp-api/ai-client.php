<?php
/**
 * The AI client: the SDK pieces and the WordPress adapters in wp-api/ai-client/,
 * loaded the first time anything names one (Austin, D5: written clean-room).
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
    foreach (['Psr', 'Psr7', 'Sdk', 'Adapters'] as $file) {
        require_once __DIR__ . '/ai-client/' . $file . '.php';
    }
}
