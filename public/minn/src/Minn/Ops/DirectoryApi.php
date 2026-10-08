<?php

declare(strict_types=1);

namespace Minn\Ops;

use Closure;

/**
 * plugins_api() and themes_api() as plugins call them and answer them
 * (probes plugins-api, themes-api), one class for both ($kind 'plugins' or
 * 'themes'): the arguments as an object with the reader's locale and the
 * major.minor version beside them, through <kind>_api_args; then whatever a
 * plugin answers through <kind>_api (a seller's own information, or an
 * error) stands, and only when nobody answers is the directory asked,
 * through Ops\Packages (behind the Minn update service);
 * <kind>_api_result is handed what came back either way. A refusal is
 * <kind>_api_failed in the directory's own words.
 */
final readonly class DirectoryApi
{
    /** @param Closure(string, mixed...): mixed $filter applies a filter, as apply_filters does */
    public function __construct(private string $kind, private Closure $filter, private Packages $packages, private string $locale, private string $version)
    {
    }

    /** The answer for an action (plugin_information, query_themes, ...): an object, a WP_Error, or what a plugin returned. */
    public function ask(string $action, array|object $args): mixed
    {
        $args = is_object($args) ? $args : (object) $args;
        $args->locale ??= $this->locale;
        $args->wp_version ??= substr($this->version, 0, 3);
        $args = ($this->filter)("{$this->kind}_api_args", $args, $action);
        $answer = ($this->filter)("{$this->kind}_api", false, $action, $args);
        if ($answer === false) {
            $answer = $this->directory($action, (array) $args);
        }
        return ($this->filter)("{$this->kind}_api_result", $answer, $action, $args);
    }

    /** @param array<string, mixed> $request */
    private function directory(string $action, array $request): object
    {
        $data = $this->packages->infoAction($this->kind, $action, $request);
        if ($data === null) {
            return new \WP_Error("{$this->kind}_api_failed", $this->kind === 'themes' ? 'The theme directory did not answer.' : 'The plugin directory did not answer.');
        }
        if (isset($data['unexpected'])) {
            return $this->unexpected((string) $data['unexpected']);
        }
        if (isset($data['error'])) {
            return new \WP_Error("{$this->kind}_api_failed", (string) $data['error']);
        }
        // An object at the top with arrays inside, as the reference hands it back; a theme search's themes are objects too.
        if ($this->kind === 'themes' && is_array($data['themes'] ?? null)) {
            $data['themes'] = array_map(static fn ($theme) => is_array($theme) ? (object) $theme : $theme, $data['themes']);
        }
        return (object) $data;
    }

    /**
     * An answer that is not JSON. wordpress.org words an unknown theme
     * action as JSON to WordPress but as a paragraph to anyone else (the
     * update service), so the paragraph is read as the refusal WordPress
     * would have been given; anything else is the reference's own message
     * for an answer it cannot read, the body kept as its data.
     */
    private function unexpected(string $body): \WP_Error
    {
        if ($this->kind === 'themes' && preg_match('#^\s*<p>(.+)</p>\s*$#s', $body, $paragraph) === 1) {
            return new \WP_Error('themes_api_failed', trim($paragraph[1]));
        }
        return new \WP_Error("{$this->kind}_api_failed", 'An unexpected error occurred. Something may be wrong with WordPress.org or this server&#8217;s configuration. If you continue to have problems, please try the <a href="https://wordpress.org/support/forums/">support forums</a>.', $body);
    }
}
