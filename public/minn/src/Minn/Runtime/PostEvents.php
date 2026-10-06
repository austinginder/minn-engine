<?php

declare(strict_types=1);

namespace Minn\Runtime;

use Minn\Content\PostRecord;
use Minn\Content\PostWriter;
use Minn\Http\Request;
use Minn\Media\Uploads;
use Minn\Media\Writer;
use Minn\Rest\RegisteredFields;
use Minn\Rest\RegisteredType;
use Minn\Rest\RuntimeRoutes;

/**
 * What the reference's REST controllers tell plugins about a post they
 * write, for the engine's own controllers: the same actions in the same
 * order with the same arguments, fired through the facade's lifecycle
 * functions so the facade's own writes say the same (contracts/runtime.md
 * "Writes tell plugins"). Without a booted runtime every method does
 * nothing, so a write with no plugins loaded is exactly what it was.
 */
final readonly class PostEvents
{
    /** Whether plugins are loaded to be told anything. */
    public function live(): bool
    {
        return Runtime::booted();
    }

    /** Before the row is written: pre_post_insert for a new post, pre_post_update for one that exists. @param array<string, mixed> $columns */
    public function beforeSave(array $columns, ?PostRecord $existing): void
    {
        if ($this->live()) {
            \_minn_post_before_save($existing === null ? $columns : $columns + $existing->row(), $existing?->id ?? 0);
        }
    }

    /** Once the row is written: the caches, the status transition, the edit actions of an update, the save actions. */
    public function saved(int $id, ?PostRecord $before): void
    {
        if ($this->live()) {
            \_minn_post_saved($id, $before !== null, self::wpPost($before));
        }
    }

    /**
     * A post keeps a category through every save, the default when it has
     * none: through wp_set_post_categories with plugins loaded (which tells
     * them, set_object_terms included), quietly without.
     */
    public function ensureCategory(PostWriter $writer, int $id): void
    {
        if (!$this->live()) {
            $writer->ensureCategory($id);
            return;
        }
        \wp_set_post_categories($id, \wp_get_post_categories($id));
    }

    /**
     * The terms a REST body names, set through wp_set_object_terms with
     * plugins loaded, quietly without: a plugin's type takes its own REST
     * taxonomies under their REST bases (probe rest-plugin-types).
     *
     * @param array<string, mixed> $body
     */
    public function applyTerms(PostWriter $writer, int $id, array $body, string $type = ''): void
    {
        if (!$this->live()) {
            $writer->applyTerms($id, $body);
            return;
        }
        $registered = RegisteredType::of($type);
        foreach (PostWriter::requestedTerms($body, $registered?->taxonomies()) as $taxonomy => $termIds) {
            \wp_set_object_terms($id, $termIds, $taxonomy);
        }
    }

    /**
     * The meta a REST body names for a plugin's type, through update_post_meta
     * (a list replaced value by value, null deleting): only the keys
     * registered to show in REST for the type.
     *
     * @param array<string, mixed> $body
     */
    public function applyRegisteredMeta(int $id, array $body, string $type): void
    {
        $registered = RegisteredType::of($type);
        if ($registered === null || !is_array($body['meta'] ?? null)) {
            return;
        }
        $keys = \get_registered_meta_keys('post', $type) + \get_registered_meta_keys('post');
        foreach ($body['meta'] as $key => $value) {
            $args = $keys[$key] ?? null;
            if ($args === null || empty($args['show_in_rest'])) {
                continue;
            }
            if ($value === null) {
                \delete_post_meta($id, (string) $key);
            } elseif (!empty($args['single'])) {
                \update_post_meta($id, (string) $key, $value);
            } else {
                \delete_post_meta($id, (string) $key);
                foreach ((array) $value as $one) {
                    \add_post_meta($id, (string) $key, $one);
                }
            }
        }
    }

    /** The file a new attachment holds: through add_post_meta with plugins loaded, written directly without. */
    public function attachedFile(Writer $library, int $id, string $relative): void
    {
        $this->live() ? \add_post_meta($id, '_wp_attached_file', $relative) : $library->setAttachedFile($id, $relative);
    }

    /** add_attachment, once a new attachment's row and file are in. */
    public function attachmentAdded(int $id): void
    {
        if ($this->live()) {
            \clean_post_cache($id);
            \do_action('add_attachment', $id);
        }
    }

    /** edit_attachment and attachment_updated, after an attachment's row changed. */
    public function attachmentEdited(int $id, PostRecord $before): void
    {
        if (!$this->live()) {
            return;
        }
        $was = self::wpPost($before);
        \clean_post_cache($was);
        \do_action('edit_attachment', $id);
        \do_action('attachment_updated', $id, \get_post($id), $was);
    }

    /**
     * A new attachment's metadata made by the runtime, as the reference's
     * REST upload makes it: wp_generate_attachment_metadata over the stored
     * file (the sizes cut with the attachment's id, the metadata stored as
     * each one lands, so an optimiser hooked there finds it), then
     * wp_update_attachment_metadata. Only for the images the engine sizes,
     * as without plugins.
     */
    public function attachmentGenerated(int $id, string $file, string $mime): void
    {
        if (!str_starts_with($mime, 'image/') || $mime === 'image/svg+xml' || !in_array($mime, Uploads::MIMES, true)) {
            return;
        }
        \wp_update_attachment_metadata($id, \wp_generate_attachment_metadata($id, $file));
    }

    /** An attachment's alt text: through update_post_meta with plugins loaded, written directly without. */
    public function altText(Writer $library, int $id, string $alt): void
    {
        $this->live() ? \update_post_meta($id, '_wp_attachment_image_alt', $alt) : $library->setAlt($id, $alt);
    }

    /** rest_insert_{type}, before the request's own terms and fields are applied; a post with no $before is a new one. */
    public function restInserted(int $id, Request $request, ?PostRecord $before): void
    {
        $this->rest('rest_insert_', $id, $request, $before);
    }

    /** rest_after_insert_{type}, once the request's terms and fields are in; a post with no $before is a new one. */
    public function restAfterInsert(int $id, Request $request, ?PostRecord $before): void
    {
        $this->rest('rest_after_insert_', $id, $request, $before);
    }

    /** wp_after_insert_post, last; the revision of an update is saved from it. */
    public function afterInsert(int $id, ?PostRecord $before): void
    {
        if ($this->live()) {
            \wp_after_insert_post(\get_post($id), $before !== null, self::wpPost($before));
        }
    }

    /** rest_delete_{type}, after a trash or a delete, with the post as it was answered and the response. @param array<string, mixed> $data */
    public function restDeleted(PostRecord $post, array $data, Request $request): void
    {
        if ($this->live()) {
            \do_action("rest_delete_{$post->type}", self::wpPost($post), new \WP_REST_Response($data, 200), RuntimeRoutes::wpRequest($request));
        }
    }

    /** One of the two REST insert actions, with the post as it stands and the request. */
    private function rest(string $prefix, int $id, Request $request, ?PostRecord $before): void
    {
        if (!$this->live()) {
            return;
        }
        $post = \get_post($id);
        if ($post === null) {
            return;
        }
        $wpRequest = RuntimeRoutes::wpRequest($request);
        if ($prefix === 'rest_after_insert_') {
            RegisteredFields::update($post, $post->post_type, $wpRequest);
            RegisteredFields::context($wpRequest, $post->post_type);
        }
        \do_action($prefix . $post->post_type, $post, $wpRequest, $before === null);
    }

    /** A record as the runtime's post object, for a state the database no longer holds. */
    private static function wpPost(?PostRecord $post): ?\WP_Post
    {
        return $post === null ? null : new \WP_Post((object) $post->row());
    }
}
