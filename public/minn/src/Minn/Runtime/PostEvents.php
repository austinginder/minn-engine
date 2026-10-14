<?php

declare(strict_types=1);

namespace Minn\Runtime;

use Minn\Content\PostRecord;
use Minn\Content\PostWriter;
use Minn\Http\Request;
use Minn\Objects\Post;
use Minn\Rest\RegisteredFields;
use Minn\Rest\RegisteredType;
use Minn\Rest\RestMeta;
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
    /**
     * A post deleted for good through the reference's own deletion, so its
     * hooks fire and its children (revisions, autosaves) go with it: the
     * one path every permanent delete takes. Deleting needs the facade, so
     * unlike the events it runs only under a booted runtime, which the
     * engine's write routes are. Answers what the deletion answered.
     */
    public function deleteForGood(int $id): mixed
    {
        return \wp_delete_post($id, true);
    }

    /** A post (an attachment too) sent to the trash through the reference's own trashing, as deleteForGood deletes. */
    public function trash(int $id): mixed
    {
        return \wp_trash_post($id);
    }

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
        // A post with none is handed the default first, as the reference's insert hands it on (wp_set_post_categories only hears an empty list from a caller that passes one).
        $categories = PostReads::categories($id);
        \wp_set_post_categories($id, $categories === [] ? [(int) Runtime::options()->filtered('default_category')] : $categories);
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
            TermWrites::setObjectTerms($id, $termIds, $taxonomy);
        }
    }

    /**
     * The meta a REST body names: through the registered keys with plugins
     * loaded (probe rest-meta; a plugin's type only when it supports custom
     * fields), core's own written directly without.
     *
     * @param array<string, mixed> $body
     */
    public function applyMeta(PostWriter $writer, int $id, array $body, string $type): void
    {
        if (!$this->live()) {
            $writer->applyCoreMeta($id, $body, $type);
            return;
        }
        $registered = RegisteredType::of($type);
        if (is_array($body['meta'] ?? null) && ($registered === null || $registered->supports('custom-fields'))) {
            RestMeta::write('post', $id, $type, $body['meta']);
        }
    }

    /**
     * A new attachment's metadata made by the runtime, as the reference's
     * REST upload makes it: wp_generate_attachment_metadata over the stored
     * file (the sizes cut with the attachment's id, the metadata stored as
     * each one lands, so an optimiser hooked there finds it), then
     * wp_update_attachment_metadata. Every upload has some: an image its
     * sizes, a PDF its previews, any file its size (probe hook-inputs-2).
     */
    public function attachmentGenerated(int $id, string $file): void
    {
        \wp_update_attachment_metadata($id, \wp_generate_attachment_metadata($id, $file));
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
            \wp_after_insert_post(Records::post($id), $before !== null, self::wpPost($before));
        }
    }

    /** rest_delete_{type}, after a trash or a delete, with the post as it was answered and the response. @param array<string, mixed> $data */
    public function restDeleted(PostRecord $post, array $data, Request $request): void
    {
        if ($this->live()) {
            Runtime::hooks()->action("rest_delete_{$post->type}", [self::wpPost($post), Handoff::newRestResponse($data, 200), RuntimeRoutes::wpRequest($request)]);
        }
    }

    /** One of the two REST insert actions, with the post as it stands and the request. */
    private function rest(string $prefix, int $id, Request $request, ?PostRecord $before): void
    {
        if (!$this->live()) {
            return;
        }
        $post = Records::post($id);
        if ($post === null) {
            return;
        }
        $wpRequest = RuntimeRoutes::wpRequest($request);
        if ($prefix === 'rest_after_insert_') {
            // An attachment's meta is written here; a post's, with its terms (applyMeta).
            if ($post->post_type === 'attachment') {
                RestMeta::writeFrom($wpRequest, 'post', $id, 'attachment');
            }
            RegisteredFields::update($post, $post->post_type, $wpRequest);
            RegisteredFields::context($wpRequest, $post->post_type);
        }
        Runtime::hooks()->action((string) $prefix . $post->post_type, [$post, $wpRequest, $before === null]);
    }

    /** A record as the runtime's post object, for a state the database no longer holds. */
    private static function wpPost(?PostRecord $post): ?Post
    {
        return $post === null ? null : Handoff::newPost((object) $post->row());
    }
}
