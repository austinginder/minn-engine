<?php

declare(strict_types=1);

namespace Minn\Runtime;

use Closure;
use Minn\Http\Request;
use Minn\Rest\RuntimeRoutes;

/**
 * What the reference's REST terms controller tells plugins, for the
 * engine's own: with plugins loaded a term is written through the
 * runtime's wp_insert_term, wp_update_term and wp_delete_term (create_term,
 * the cache cleaning that rebuilds a taxonomy's children map, created_term,
 * saved_term and their families), and the REST actions follow. Without a
 * booted runtime each write is the engine's own, given as a closure.
 */
final readonly class TermEvents
{
    /** Whether plugins are loaded to be told anything. */
    public function live(): bool
    {
        return Runtime::booted();
    }

    /**
     * Creates a term and returns its id.
     *
     * @param array{slug: string, description: string, parent: int} $args
     * @param Closure(): int $quietly the engine's own write
     */
    public function create(string $name, string $taxonomy, array $args, Closure $quietly): int
    {
        if (!$this->live()) {
            return $quietly();
        }
        $made = \wp_insert_term($name, $taxonomy, $args);
        return is_array($made) ? (int) $made['term_id'] : $quietly();
    }

    /**
     * Updates a term's name, slug, description or parent.
     *
     * @param array<string, mixed> $args the fields the request changes
     * @param Closure(): void $quietly the engine's own write
     */
    public function update(int $termId, string $taxonomy, array $args, Closure $quietly): void
    {
        $this->live() ? \wp_update_term($termId, $taxonomy, $args) : $quietly();
    }

    /**
     * Deletes a term, then tells plugins over REST with the term as it was and the response.
     *
     * @param array<string, mixed> $data the response
     * @param Closure(): void $quietly the engine's own delete
     */
    public function delete(int $termId, string $taxonomy, array $data, Request $request, Closure $quietly): void
    {
        if (!$this->live()) {
            $quietly();
            return;
        }
        $term = \get_term($termId, $taxonomy);
        \wp_delete_term($termId, $taxonomy);
        \do_action("rest_delete_{$taxonomy}", $term, new \WP_REST_Response($data, 200), RuntimeRoutes::wpRequest($request));
    }

    /** rest_insert_{taxonomy}, then rest_after_insert_{taxonomy}, with the term as it stands and the request. */
    public function restSaved(int $termId, string $taxonomy, Request $request, string $verb): void
    {
        if (!$this->live()) {
            return;
        }
        $wpRequest = RuntimeRoutes::wpRequest($request);
        \do_action("rest_insert_{$taxonomy}", \get_term($termId, $taxonomy), $wpRequest, $verb === 'create');
        \do_action("rest_after_insert_{$taxonomy}", \get_term($termId, $taxonomy), $wpRequest, $verb === 'create');
    }
}
