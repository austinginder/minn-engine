<?php

declare(strict_types=1);

namespace Minn\Rest;

use Minn\Auth\RegisteredCaps;
use Minn\Runtime\TermEvents;
use Minn\Http\Policy;
use Minn\Http\Args;
use Minn\Http\Subject;
use Minn\Http\Access;
use Minn\Content\TermRecord;
use Minn\Content\Site;
use Minn\Content\Terms;
use Minn\Db;
use Minn\Http\Method;
use Minn\Http\Request;
use Minn\Http\Response;
use Minn\Http\Route;
use Minn\RestError;
use Minn\Support\Kses;

/** wp/v2 categories, tags, and pattern categories: list, single, and the create/update/delete the taxonomy admin drives. */
final readonly class TermsController
{
    public function __construct(
        private Db $db,
        private Terms $terms,
        private Site $site,
        private TermObject $object,
        private Caller $caller,
    ) {
    }

    /**
     * A taxonomy's term list as the reference serves it: the request's
     * get_terms (wp_get_object_terms for a post's) through
     * rest_{taxonomy}_collection_params and rest_{taxonomy}_query, counted
     * by wp_count_terms without the page.
     */
    #[Route(Method::Get, '/wp/v2/{base:categories|tags|wp_pattern_category}', policy: new Policy(Access::Public), params: TermCollectionParams::class)]
    public function list(Request $request, string $base): Response
    {
        $taxonomy = TermObject::config($base)['taxonomy'];
        $params = TermCollectionParams::for(['base' => $base]);
        $wp = RuntimeRoutes::sanitized($request, $params);
        $object = \get_taxonomy($taxonomy);
        if (Context::of($request)->isEdit() && !$this->caller->can((string) ($object->cap->edit_terms ?? 'manage_categories'))) {
            throw $this->caller->refuse('rest_forbidden_context', 'Sorry, you are not allowed to edit terms in this taxonomy.');
        }
        $post = isset($wp['post']) ? (int) $wp['post'] : 0;
        if (isset($wp['post']) && \get_post($post) === null) {
            throw new RestError('rest_post_invalid_id', 'Invalid post ID.', 400);
        }
        $registered = (array) \apply_filters("rest_{$taxonomy}_collection_params", $params, $object);
        $args = (array) \apply_filters("rest_{$taxonomy}_query", TermListArgs::of($wp, $registered, $taxonomy, $request->method->value), $wp);
        $found = empty($args['post']) ? \get_terms($args) : \wp_get_object_terms($args['post'], $taxonomy, $args);
        $count = empty($args['post']) ? $args : ['object_ids' => $args['post']] + $args;
        unset($count['number'], $count['offset']);
        $total = (int) \wp_count_terms($count);
        $perPage = (int) ($args['number'] ?? 0);
        $pages = $perPage > 0 ? (int) ceil($total / $perPage) : 0;
        if ($request->method === Method::Head) {
            return Reply::list([], $total, $pages, null);
        }
        $objects = [];
        foreach (is_array($found) ? $found : [] as $term) {
            if ($term instanceof \WP_Term) {
                $objects[] = $this->object->view(TermRecord::fromRow(get_object_vars($term)), $base);
            }
        }
        return Reply::list($objects, $total, $pages, Fields::fromQuery($request->query));
    }

    /** One category or tag. */
    #[Route(Method::Get, '/wp/v2/{base:categories|tags|wp_pattern_category}/{id:[\d]+}', policy: new Policy(Access::Public, subject: Subject::Term, param: 'id', edit: new Policy(Access::Cap, 'manage_categories', signIn: 'rest_forbidden_context', signInMessage: 'Sorry, you are not allowed to edit this term.', refuse: 'rest_forbidden_context', message: 'Sorry, you are not allowed to edit this term.')), args: [Args::CONTEXT])]
    public function single(Request $request, string $base, string $id): Response
    {
        $config = TermObject::config($base);
        $row = $this->db->row(
            "SELECT t.term_id, t.name, t.slug, tt.description, tt.count, tt.parent
             FROM {$this->db->table('terms')} t
             JOIN {$this->db->table('term_taxonomy')} tt ON tt.term_id = t.term_id
             WHERE tt.taxonomy = ? AND t.term_id = ? LIMIT 1",
            [$config['taxonomy'], (int) $id],
        );
        if ($row === null) {
            throw new RestError('rest_term_invalid', 'Term does not exist.', 404);
        }
        if (Context::of($request)->isEdit() && !$this->caller->can('edit_term', (int) $id)) {
            throw $this->caller->refuse('rest_forbidden_context', 'Sorry, you are not allowed to edit this term.');
        }
        return Reply::item($this->object->view(TermFilters::one(TermRecord::fromRow($row), $config['taxonomy']), $base), Fields::fromQuery($request->query));
    }

    /** Tags and pattern categories are open to edit_posts holders; categories need manage_categories. */
    #[Route(Method::Post, '/wp/v2/{base:categories}', policy: new Policy(Access::Cap, 'manage_categories', signIn: 'rest_cannot_create', signInMessage: 'Sorry, you are not allowed to create terms in this taxonomy.', refuse: 'rest_cannot_create', message: 'Sorry, you are not allowed to create terms in this taxonomy.'))]
    #[Route(Method::Post, '/wp/v2/{base:tags|wp_pattern_category}', policy: new Policy(Access::Cap, 'edit_posts', signIn: 'rest_cannot_create', signInMessage: 'Sorry, you are not allowed to create terms in this taxonomy.', refuse: 'rest_cannot_create', message: 'Sorry, you are not allowed to create terms in this taxonomy.'))]
    public function create(Request $request, string $base): Response
    {
        $config = TermObject::config($base);
        $taxonomy = $config['taxonomy'];
        $refusal = 'Sorry, you are not allowed to create terms in this taxonomy.';
        $this->caller->require('rest_cannot_create', $refusal);
        if (!$this->caller->can(self::createCapability($taxonomy))) {
            throw new RestError('rest_cannot_create', $refusal, 403);
        }
        $body = $request->json();
        $name = Kses::text((string) ($body['name'] ?? ''));
        if ($name === '') {
            throw RestError::missingParams(['name']);
        }
        $this->requireParent($config['has_parent'] ? (int) ($body['parent'] ?? 0) : 0, $taxonomy);
        $events = new TermEvents();
        if ($events->live()) {
            $termId = $events->restCreate($taxonomy, $body, $request);
            $events->restSaved($termId, $taxonomy, $request, 'create');
            return Reply::item($this->object->view($this->terms->row($termId, $taxonomy), $base), Fields::fromQuery($request->query), 201)
                ->withHeader('Location', $this->object->url()->to("/wp/v2/{$base}/{$termId}"));
        }
        // A same-name term is the reference's term_exists refusal, which hands
        // the existing id back in data AND in an additional_data list.
        $existing = $this->terms->idByName($name, $taxonomy);
        if ($existing !== null) {
            throw new RestError(
                'term_exists',
                'A term with the name provided already exists in this taxonomy.',
                400,
                ['term_id' => $existing],
                ['additional_data' => [$existing, $existing]],
            );
        }
        $slug = $this->terms->uniqueSlug((string) ($body['slug'] ?? '') !== '' ? (string) $body['slug'] : $name, $taxonomy);
        $args = ['slug' => $slug, 'description' => Kses::comment((string) ($body['description'] ?? '')), 'parent' => $config['has_parent'] ? (int) ($body['parent'] ?? 0) : 0];
        $termId = $this->terms->create($name, $slug, $taxonomy, $args['description'], $args['parent']);
        $events->restSaved($termId, $taxonomy, $request, 'create');
        return Reply::item($this->object->view($this->terms->row($termId, $taxonomy), $base), Fields::fromQuery($request->query), 201)
            ->withHeader('Location', $this->object->url()->to("/wp/v2/{$base}/{$termId}"));
    }

    /** Updates a category or tag. */
    #[Route(Method::Post, '/wp/v2/{base:categories|tags|wp_pattern_category}/{id:[\d]+}', policy: new Policy(Access::Cap, 'manage_categories', param: 'id', subject: Subject::Term, signIn: 'rest_cannot_update', signInMessage: 'Sorry, you are not allowed to edit this term.', refuse: 'rest_cannot_update', message: 'Sorry, you are not allowed to edit this term.'))]
    #[Route(Method::Put, '/wp/v2/{base:categories|tags|wp_pattern_category}/{id:[\d]+}', policy: new Policy(Access::Cap, 'manage_categories', param: 'id', subject: Subject::Term, signIn: 'rest_cannot_update', signInMessage: 'Sorry, you are not allowed to edit this term.', refuse: 'rest_cannot_update', message: 'Sorry, you are not allowed to edit this term.'))]
    #[Route(Method::Patch, '/wp/v2/{base:categories|tags|wp_pattern_category}/{id:[\d]+}', policy: new Policy(Access::Cap, 'manage_categories', param: 'id', subject: Subject::Term, signIn: 'rest_cannot_update', signInMessage: 'Sorry, you are not allowed to edit this term.', refuse: 'rest_cannot_update', message: 'Sorry, you are not allowed to edit this term.'))]
    public function update(Request $request, string $base, string $id): Response
    {
        $config = TermObject::config($base);
        $taxonomy = $config['taxonomy'];
        $termId = (int) $id;
        $term = $this->terms->row($termId, $taxonomy);
        if ($term === null) {
            throw new RestError('rest_term_invalid', 'Term does not exist.', 404);
        }
        if (!$this->caller->can('edit_term', $termId)) {
            throw $this->caller->refuse('rest_cannot_update', 'Sorry, you are not allowed to edit this term.');
        }
        $body = $request->json();
        $this->requireParent($config['has_parent'] ? (int) ($body['parent'] ?? 0) : 0, $taxonomy);
        $events = new TermEvents();
        if ($events->live()) {
            $events->restUpdate($termId, $taxonomy, $body, $request);
            $events->restSaved($termId, $taxonomy, $request, 'update');
            return Reply::item($this->object->view($this->terms->row($termId, $taxonomy), $base), Fields::fromQuery($request->query));
        }
        $args = array_filter([
            'name' => isset($body['name']) ? Kses::text((string) $body['name']) : null,
            'slug' => isset($body['slug']) ? $this->terms->uniqueSlug((string) $body['slug'], $taxonomy, $termId) : null,
            'description' => isset($body['description']) ? Kses::comment((string) $body['description']) : null,
            'parent' => $config['has_parent'] && isset($body['parent']) ? (int) $body['parent'] : null,
        ], static fn ($v) => $v !== null);
        if (isset($args['name']) || isset($args['slug'])) {
            $this->terms->rename($termId, (string) ($args['name'] ?? $term['name']), (string) ($args['slug'] ?? $term['slug']));
        }
        if (isset($args['description']) || isset($args['parent'])) {
            $this->terms->describe($termId, $taxonomy, (string) ($args['description'] ?? $term['description']), (int) ($args['parent'] ?? $term['parent']));
        }
        return Reply::item($this->object->view($this->terms->row($termId, $taxonomy), $base), Fields::fromQuery($request->query));
    }

    /** The reference's refusal of a parent that is not there, before anything is saved. */
    private function requireParent(int $parent, string $taxonomy): void
    {
        if ($parent > 0 && $this->terms->row($parent, $taxonomy) === null) {
            throw new RestError('rest_term_invalid', 'Parent term does not exist.', 400);
        }
    }

    /** The default category is capability-denied before the force check. */
    #[Route(Method::Delete, '/wp/v2/{base:categories|tags|wp_pattern_category}/{id:[\d]+}', policy: new Policy(Access::Cap, 'manage_categories', param: 'id', subject: Subject::Term, signIn: 'rest_cannot_delete', signInMessage: 'Sorry, you are not allowed to delete this term.', refuse: 'rest_cannot_delete', message: 'Sorry, you are not allowed to delete this term.'))]
    public function delete(Request $request, string $base, string $id): Response
    {
        $config = TermObject::config($base);
        $taxonomy = $config['taxonomy'];
        $termId = (int) $id;
        $term = $this->terms->row($termId, $taxonomy);
        if ($term === null) {
            throw new RestError('rest_term_invalid', 'Term does not exist.', 404);
        }
        if (!$this->caller->can('delete_term', $termId)) {
            throw $this->caller->refuse('rest_cannot_delete', 'Sorry, you are not allowed to delete this term.');
        }
        if ($taxonomy === 'category' && $termId === (int) ($this->site->option('default_category') ?? 0)) {
            throw new RestError('rest_cannot_delete', 'Sorry, you are not allowed to delete this term.', 403);
        }
        if (!$request->flag('force')) {
            throw new RestError('rest_trash_not_supported', "Terms do not support trashing. Set 'force=true' to delete.", 501);
        }
        $data = ['deleted' => true, 'previous' => array_diff_key($this->object->view($term, $base), ['_links' => true])];
        (new TermEvents())->delete($termId, $taxonomy, $data, $request, fn () => $this->terms->delete(TermRecord::fromRow($term->row() + ['taxonomy' => $taxonomy]), $config['has_parent']));
        return Reply::item($data, Fields::fromQuery($request->query));
    }

    /**
     * What creating a term needs (probe rest-plugin-caps): a plugin's
     * taxonomy, its edit_terms when hierarchical and its assign_terms when
     * flat; categories, manage_categories; the others, edit_posts.
     */
    public static function createCapability(string $taxonomy): string
    {
        $registered = RegisteredCaps::ofTaxonomy($taxonomy);
        if ($registered === null) {
            return $taxonomy === 'category' ? 'manage_categories' : 'edit_posts';
        }
        return (string) ($registered->hierarchical ? $registered->cap->edit_terms : $registered->cap->assign_terms);
    }
}
