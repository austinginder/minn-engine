<?php

use Minn\Rest\ViewConfigPatch;

/**
 * A view configuration a plugin may patch (probe rest-view-config): set,
 * merge, replace and remove, each for schema version 1 and the documented
 * keys only (Minn\Rest\ViewConfigPatch), each answering the data itself so
 * patches chain. apply_filters hands it to
 * get_entity_view_config_{kind}_{name} and answers the result without its
 * version. Each mistake is a _doing_it_wrong.
 */
class WP_View_Config_Data
{
    private $config;
    private $defaults;

    public function __construct(array $config)
    {
        $this->config = $config;
        $this->defaults = $config;
    }

    public function apply_filters($kind, $name)
    {
        apply_filters('get_entity_view_config_' . strtolower((string) $kind) . '_' . $name, $this, ['kind' => $kind, 'name' => $name]);
        $data = $this->config;
        unset($data['version']);
        return $data;
    }

    public function set(array $patch, $version)
    {
        return $this->minn_apply($patch, $version, 'set');
    }

    public function remove(array $spec, $version)
    {
        return $this->minn_apply($spec, $version, 'remove');
    }

    public function replace(array $patch, $version)
    {
        return $this->minn_apply($patch, $version, 'replace');
    }

    public function merge(array $patch, $version)
    {
        return $this->minn_apply($patch, $version, 'merge');
    }

    private function get_data()
    {
        return $this->config;
    }

    /** A patch applied, each problem a _doing_it_wrong naming the method (or the step) that met it. */
    private function minn_apply(array $patch, $version, string $mode)
    {
        [$this->config, $problems] = ViewConfigPatch::apply($this->config, $patch, $version, $mode);
        foreach ($problems as [$function, $message]) {
            _doing_it_wrong(__CLASS__ . '::' . ($function ?? $mode), esc_html($message), '7.1.0');
        }
        return $this;
    }
}
