<?php

declare(strict_types=1);

namespace Minn\Runtime;

use Minn\Content\PostRecord;
use Minn\Content\PostWriter;
use Minn\Http\Request;
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

    /** The terms a REST body names, set through wp_set_object_terms with plugins loaded, quietly without. @param array<string, mixed> $body */
    public function applyTerms(PostWriter $writer, int $id, array $body): void
    {
        if (!$this->live()) {
            $writer->applyTerms($id, $body);
            return;
        }
        foreach (PostWriter::requestedTerms($body) as $taxonomy => $termIds) {
            \wp_set_object_terms($id, $termIds, $taxonomy);
        }
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
        if ($post !== null) {
            \do_action($prefix . $post->post_type, $post, RuntimeRoutes::wpRequest($request), $before === null);
        }
    }

    /** A record as the runtime's post object, for a state the database no longer holds. */
    private static function wpPost(?PostRecord $post): ?\WP_Post
    {
        return $post === null ? null : new \WP_Post((object) $post->row());
    }
}
