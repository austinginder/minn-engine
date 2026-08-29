<?php

use Minn\Runtime\Runtime;

/** The Interactivity API object wp_interactivity() returns; every method maps onto the runtime's processor. */
class WP_Interactivity_API
{
    private static ?self $instance = null;

    public static function instance(): self
    {
        return self::$instance ??= new self();
    }

    public function state(?string $store_namespace = null, array $state = []): array
    {
        return Runtime::interactivity()->state($store_namespace, $state);
    }

    public function config(string $store_namespace, array $config = []): array
    {
        return Runtime::interactivity()->config($store_namespace, $config);
    }

    public function get_context(?string $store_namespace = null): array
    {
        return Runtime::interactivity()->context($store_namespace);
    }

    public function get_element(): ?array
    {
        return Runtime::interactivity()->element();
    }

    public function process_directives(string $html): string
    {
        return Runtime::interactivity()->process($html);
    }

    public function data_wp_context(array $context, string $store_namespace = ''): string
    {
        return wp_interactivity_data_wp_context($context, $store_namespace);
    }

    public function add_hooks(): void
    {
    }
}
