<?php

use Minn\Content\Site;
use Minn\Runtime\Recovery;
use Minn\Runtime\Runtime;

/**
 * The paused plugins or themes, through WordPress's storage class (probe
 * recovery). WordPress keeps them for a recovery session; Minn keeps no
 * session and pauses on its own (Runtime\Recovery, Austin's D4), so this
 * answers from the engine's pause records: get() and get_all() read them,
 * delete() and delete_all() let the extension load again, and set() is
 * refused, as the reference refuses it outside a session. A plugin is found
 * by its plugin file or its folder; a type other than plugin or theme has
 * nothing paused.
 */
#[AllowDynamicProperties]
class WP_Paused_Extensions_Storage
{
    protected $type;

    public function __construct($extension_type)
    {
        $this->type = $extension_type;
    }

    /** Minn pauses an extension itself, after a second boot failure; nothing else records one. */
    public function set($extension, $error)
    {
        return false;
    }

    public function delete($extension)
    {
        $key = $this->key((string) $extension);
        return $key !== null && $this->recovery()->resume((string) $this->type, $key);
    }

    public function get($extension)
    {
        $key = $this->key((string) $extension);
        return $key === null ? null : $this->get_all()[$key];
    }

    public function get_all()
    {
        return $this->get_option_name() === '' || !$this->is_api_loaded() ? [] : $this->recovery()->paused((string) $this->type);
    }

    public function delete_all()
    {
        return $this->get_option_name() !== '' && $this->is_api_loaded() && $this->recovery()->resumeKind((string) $this->type) > 0;
    }

    /** Whether the options API is there to read. */
    protected function is_api_loaded()
    {
        return function_exists('get_option');
    }

    /** The option the engine keeps this type's pauses in; empty for a type it never pauses. */
    protected function get_option_name()
    {
        return match ($this->type) {
            'plugin' => Recovery::PLUGINS_OPTION,
            'theme' => Recovery::THEMES_OPTION,
            default => '',
        };
    }

    /** The paused entry an extension names: its own key, or the plugin file inside a folder of that name. */
    private function key(string $extension): ?string
    {
        $paused = $this->get_all();
        if ($extension === '' || isset($paused[$extension])) {
            return $extension === '' ? null : $extension;
        }
        foreach (array_keys($paused) as $name) {
            if (dirname((string) $name) === $extension) {
                return (string) $name;
            }
        }
        return null;
    }

    private function recovery(): Recovery
    {
        return new Recovery(new Site(Runtime::current()->db), WP_CONTENT_DIR);
    }
}
