<?php

declare(strict_types=1);

namespace Minn\Ops;

use Closure;

/**
 * plugins_api() as plugins call it and answer it (probe plugins-api): the
 * arguments as an object with the reader's locale and the major.minor
 * version beside them, through plugins_api_args; then whatever a plugin
 * answers through plugins_api (a self-hosted plugin's own information, or
 * an error) stands, and only when nobody answers is the directory asked,
 * through Ops\Packages (the engine's one door to it, which Track H moves
 * behind the Minn update service); plugins_api_result is handed what came
 * back either way.
 */
final readonly class PluginsApi
{
    /** @param Closure(string, mixed...): mixed $filter applies a filter, as apply_filters does */
    public function __construct(private Closure $filter, private Packages $packages, private string $locale, private string $version)
    {
    }

    /** The answer for an action (plugin_information, query_plugins, ...): an object, a WP_Error, or what a plugin returned. */
    public function ask(string $action, array|object $args): mixed
    {
        $args = is_object($args) ? $args : (object) $args;
        $args->locale ??= $this->locale;
        $args->wp_version ??= substr($this->version, 0, 3);
        $args = ($this->filter)('plugins_api_args', $args, $action);
        $answer = ($this->filter)('plugins_api', false, $action, $args);
        if ($answer === false) {
            $answer = $this->directory($action, (array) $args);
        }
        return ($this->filter)('plugins_api_result', $answer, $action, $args);
    }

    /** @param array<string, mixed> $request */
    private function directory(string $action, array $request): object
    {
        $data = $this->packages->pluginsAction($action, $request);
        if ($data === null) {
            return new \WP_Error('plugins_api_failed', 'The plugin directory did not answer.');
        }
        if (isset($data['error'])) {
            return new \WP_Error('plugins_api_failed', (string) $data['error']);
        }
        // The directory's answer is an object at the top, its parts arrays, as the reference hands it back.
        return (object) $data;
    }
}
