<?php

/**
 * Queues term, comment and site ids whose meta is wanted soon, so the first
 * read of one loads them all (probe runtime-internals). Queueing hooks the
 * type's get_{type}_metadata filter; that read primes every queued id
 * (with the one asked for), empties the queue and unhooks again.
 */
#[AllowDynamicProperties]
class WP_Metadata_Lazyloader
{
    protected $pending_objects;
    protected $settings = [];

    public function __construct()
    {
        foreach (['term', 'comment', 'blog'] as $type) {
            $this->settings[$type] = ['filter' => "get_{$type}_metadata", 'callback' => [$this, 'lazyload_meta_callback']];
        }
    }

    /** Adds ids to a type's queue and hooks its filter (even for none); a WP_Error for an unknown type. */
    public function queue_objects($object_type, $object_ids)
    {
        if (!isset($this->settings[$object_type])) {
            return new WP_Error('invalid_object_type', __('Invalid object type.'));
        }
        $this->pending_objects[$object_type] ??= [];
        foreach ($object_ids as $object_id) {
            $this->pending_objects[$object_type][$object_id] ??= 1;
        }
        add_filter($this->settings[$object_type]['filter'], $this->settings[$object_type]['callback'], 10, 5);
        do_action('metadata_lazyloader_queued_objects', $object_ids, $object_type, $this);
    }

    /** Empties a type's queue and unhooks its filter; a WP_Error for an unknown type. */
    public function reset_queue($object_type)
    {
        if (!isset($this->settings[$object_type])) {
            return new WP_Error('invalid_object_type', __('Invalid object type.'));
        }
        $this->pending_objects[$object_type] = [];
        remove_filter($this->settings[$object_type]['filter'], $this->settings[$object_type]['callback']);
    }

    /** @deprecated 6.3.0 Loads the queued terms' meta; use lazyload_meta_callback(). */
    public function lazyload_term_meta($check)
    {
        _deprecated_function(__METHOD__, '6.3.0', 'WP_Metadata_Lazyloader::lazyload_meta_callback');
        return $this->lazyload_meta_callback($check, 0, '', false, 'term');
    }

    /** @deprecated 6.3.0 Loads the queued comments' meta; use lazyload_meta_callback(). */
    public function lazyload_comment_meta($check)
    {
        _deprecated_function(__METHOD__, '6.3.0', 'WP_Metadata_Lazyloader::lazyload_meta_callback');
        return $this->lazyload_meta_callback($check, 0, '', false, 'comment');
    }

    /** On a meta read: loads the type's queued ids (and the one read), empties the queue, and hands the check back untouched. */
    public function lazyload_meta_callback($check, $object_id, $meta_key, $single, $meta_type)
    {
        if (empty($this->pending_objects[$meta_type])) {
            return $check;
        }
        $ids = array_keys($this->pending_objects[$meta_type]);
        if ($object_id && !in_array($object_id, $ids, false)) {
            $ids[] = $object_id;
        }
        update_meta_cache($meta_type, $ids);
        $this->reset_queue($meta_type);
        return $check;
    }
}
