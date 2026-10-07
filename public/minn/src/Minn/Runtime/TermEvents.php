<?php

declare(strict_types=1);

namespace Minn\Runtime;

use Closure;
use Minn\Http\Request;
use Minn\RestError;
use Minn\Rest\RegisteredFields;
use Minn\Rest\RuntimeRoutes;
use Minn\Rest\RestMeta;
use Minn\Support\Slashes;

/**
 * What the reference's REST terms controller tells plugins, for the
 * engine's own: with plugins loaded a term is saved as that controller
 * saves one (rest_pre_insert_{taxonomy}, then the runtime's wp_insert_term
 * or wp_update_term, and wp_delete_term), and the REST actions follow.
 * Without a booted runtime each write is the engine's own.
 */
final readonly class TermEvents
{
    /** Whether plugins are loaded to be told anything. */
    public function live(): bool
    {
        return Runtime::booted();
    }

    /**
     * A term created over REST with plugins loaded, as the reference's
     * controller creates one (probe rest-term-save): the request's fields
     * as the prepared term, through rest_pre_insert_{taxonomy}, into
     * wp_insert_term; a refusal is its REST error.
     *
     * @param array<string, mixed> $body
     */
    public function restCreate(string $taxonomy, array $body, Request $request): int
    {
        $prepared = self::prepared($taxonomy, $body, $request);
        $made = \wp_insert_term(Slashes::add($prepared['name'] ?? null), $taxonomy, Slashes::add($prepared));
        if ($made instanceof \WP_Error) {
            throw self::refusal($made);
        }
        return (int) $made['term_id'];
    }

    /**
     * A term changed over REST with plugins loaded: the fields the request
     * sends, through rest_pre_insert_{taxonomy}, into wp_update_term (when
     * any are left); a refusal is its REST error.
     *
     * @param array<string, mixed> $body
     */
    public function restUpdate(int $termId, string $taxonomy, array $body, Request $request): void
    {
        $prepared = self::prepared($taxonomy, $body, $request);
        if ($prepared === []) {
            return;
        }
        $changed = \wp_update_term($termId, $taxonomy, Slashes::add($prepared));
        if ($changed instanceof \WP_Error) {
            throw self::refusal($changed);
        }
    }

    /**
     * The prepared term: the fields the request sends, in the reference's
     * order, the name and slug cleaned as its schema cleans them; then
     * rest_pre_insert_{taxonomy}.
     *
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    private static function prepared(string $taxonomy, array $body, Request $request): array
    {
        $prepared = [];
        if (isset($body['name'])) {
            $prepared['name'] = \sanitize_text_field((string) $body['name']);
        }
        if (isset($body['slug'])) {
            $prepared['slug'] = \sanitize_title((string) $body['slug']);
        }
        if (isset($body['description'])) {
            $prepared['description'] = (string) $body['description'];
        }
        if (isset($body['parent']) && \is_taxonomy_hierarchical($taxonomy)) {
            $prepared['parent'] = (int) $body['parent'];
        }
        $filtered = \apply_filters("rest_pre_insert_{$taxonomy}", (object) $prepared, RuntimeRoutes::wpRequest($request));
        return is_object($filtered) ? get_object_vars($filtered) : [];
    }

    /** A save's refusal as REST serves it: a name in use with the term's id, anything else with its own status or 500. */
    private static function refusal(\WP_Error $error): RestError
    {
        $existing = (int) $error->get_error_data('term_exists');
        if ($error->get_error_code() === 'term_exists' && $existing > 0) {
            return new RestError('term_exists', $error->get_error_message(), 400, ['term_id' => $existing], ['additional_data' => [$existing, $existing]]);
        }
        $data = $error->get_error_data();
        return is_array($data) && isset($data['status']) ? new RestError($error->get_error_code(), $error->get_error_message(), (int) $data['status']) : RestError::bare($error->get_error_code(), $error->get_error_message());
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
        RestMeta::writeFrom($wpRequest, 'term', $termId, $taxonomy);
        RegisteredFields::update(\get_term($termId, $taxonomy), $taxonomy, $wpRequest);
        RegisteredFields::context($wpRequest, $taxonomy);
        \do_action("rest_after_insert_{$taxonomy}", \get_term($termId, $taxonomy), $wpRequest, $verb === 'create');
    }
}
