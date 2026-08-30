<?php

use Minn\Runtime\Connectors;

/** The connectors registry plugin code reaches through wp_connectors_init: one Minn\Runtime\Connectors store per instance. */
final class WP_Connector_Registry
{
    private static $instance = null;
    private Connectors $store;

    public function __construct()
    {
        $this->store = new Connectors();
    }

    public function store(): Connectors
    {
        return $this->store;
    }

    public function register(string $id, array $args): ?array
    {
        $refused = $this->store->register($id, $args);
        if ($refused !== null) {
            _doing_it_wrong(__METHOD__, $refused->message, '7.0.0');
            return null;
        }
        return $this->store->get($id);
    }

    public function unregister(string $id): ?array
    {
        $row = $this->store->unregister($id);
        if ($row === null) {
            _doing_it_wrong(__METHOD__, sprintf('Connector "%s" not found.', $id), '7.0.0');
        }
        return $row;
    }

    public function get_all_registered(): array
    {
        return $this->store->all();
    }

    public function is_registered(string $id): bool
    {
        return $this->store->has($id);
    }

    public function get_registered(string $id): ?array
    {
        $row = $this->store->get($id);
        if ($row === null) {
            _doing_it_wrong(__METHOD__, sprintf('Connector "%s" not found.', $id), '7.0.0');
        }
        return $row;
    }

    public static function get_instance(): ?self
    {
        return self::$instance ??= new self();
    }

    public static function set_instance(WP_Connector_Registry $registry): void
    {
        if (!doing_action('init')) {
            _doing_it_wrong(__METHOD__, 'The connector registry instance must be set during the init action.', '7.0.0');
            return;
        }
        self::$instance = $registry;
    }
}
