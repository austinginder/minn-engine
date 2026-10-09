<?php

/** The registered font collections, by slug (probe fonts). */
class WP_Font_Library
{
    private $collections = [];
    private static $instance = null;

    public function register_font_collection(string $slug, array $args)
    {
        $collection = new WP_Font_Collection($slug, $args);
        if ($this->is_collection_registered($collection->slug)) {
            $message = sprintf('Font collection with slug: "%s" is already registered.', $collection->slug);
            _doing_it_wrong(__METHOD__, $message, '6.5.0');
            return new WP_Error('font_collection_registration_error', $message);
        }
        $this->collections[$collection->slug] = $collection;
        return $collection;
    }

    public function unregister_font_collection(string $slug)
    {
        if (!$this->is_collection_registered($slug)) {
            _doing_it_wrong(__METHOD__, sprintf('Font collection "%s" not found.', $slug), '6.5.0');
            return false;
        }
        unset($this->collections[$slug]);
        return true;
    }

    private function is_collection_registered(string $slug)
    {
        return isset($this->collections[$slug]);
    }

    public function get_font_collections()
    {
        return $this->collections;
    }

    public function get_font_collection(string $slug)
    {
        return $this->collections[$slug] ?? null;
    }

    public static function get_instance()
    {
        return self::$instance ??= new self();
    }
}
