<?php

declare(strict_types=1);

namespace Minn\Rest;

use Minn\Content\PostRecord;
use Minn\Content\Revisions;
use Minn\Http\Access;
use Minn\Http\Args;
use Minn\Http\Method;
use Minn\Http\Policy;
use Minn\Http\Request;
use Minn\Http\Response;
use Minn\Http\RevisionArgs;
use Minn\Http\Route;
use Minn\Objects\Errors;
use Minn\Objects\Post;
use Minn\RestError;
use Minn\Runtime\Autosaves;
use Minn\Runtime\PostEvents;
use Minn\Runtime\PostWrites;
use Minn\Runtime\Records;
use Minn\Runtime\Runtime;
use Minn\Support\Slashes;
use Minn\Theme\TemplateIndex;
use Minn\Theme\TemplateRecord;

/**
 * wp/v2/templates and wp/v2/template-parts revisions and autosaves: the
 * revision rows of a template the site saved, each shown as the template
 * it once was (the template's own shape, its parent's id, a self link and
 * a parent link). The template is judged before the caller, as on the
 * reference: one nobody saved is no parent, one only a theme file holds
 * cannot have revisions; then the caller must be signed in and able to
 * edit the saved row. The routes are listed ahead of the templates' own,
 * whose id pattern would take these paths too.
 */
final readonly class TemplateRevisionsController
{
    private const ID = '([^\/:<>\*\?"\|]+(?:\/[^\/:<>\*\?"\|]+)?)[\/\w%-]+';
    private const READ_REFUSAL = 'Sorry, you are not allowed to view revisions of this post.';
    private const AUTOSAVES_REFUSAL = 'Sorry, you are not allowed to view autosaves of this post.';
    private const DELETE_REFUSAL = 'Sorry, you are not allowed to delete revisions of this post.';
    private const MANAGE_REFUSAL = 'Sorry, you are not allowed to access the templates on this site.';

    public function __construct(
        private TemplateIndex $index,
        private TemplateObject $object,
        private Revisions $revisions,
        private RestUrl $url,
        private Caller $caller,
    ) {
    }

    /** Every revision of a saved template, its autosaves among them, filtered, ordered and paged as the request asks (RevisionCollection). */
    #[Route(Method::Get, '/wp/v2/{base:templates|template-parts}/{parent:' . self::ID . '}/revisions', policy: new Policy(Access::Public), args: [RevisionArgs::TEMPLATE_PARENT, Args::CONTEXT, RevisionArgs::COLLECTION])]
    public function revisions(Request $request, string $base, string $parent): Response
    {
        $template = $this->readable($base, $parent, self::READ_REFUSAL);
        $context = Context::of($request);
        $rows = RevisionCollection::filtered($request, PostRecord::fromRows($this->revisions->allOf($template->wpId)));
        [$shown, $total, $pages] = RevisionCollection::paged($request, $rows);
        return Reply::list(array_map(fn (PostRecord $row): array => $this->shaped($base, $template, $row, $context, 'revisions'), $shown), $total, $pages, Fields::fromQuery($request->query));
    }

    /** One revision of a saved template; a revision of another post is a mismatch. */
    #[Route(Method::Get, '/wp/v2/{base:templates|template-parts}/{parent:' . self::ID . '}/revisions/{id:[\d]+}', policy: new Policy(Access::Public), args: [RevisionArgs::TEMPLATE_PARENT, RevisionArgs::ID, Args::CONTEXT])]
    public function revision(Request $request, string $base, string $parent, string $id): Response
    {
        $template = $this->readable($base, $parent, self::READ_REFUSAL);
        $revision = RevisionCollection::ofParent($this->revisions->one((int) $id), $template->wpId);
        return Reply::item($this->shaped($base, $template, $revision, Context::of($request), 'revisions'), Fields::fromQuery($request->query));
    }

    /**
     * A template's revision deleted for good, answered as it was; never
     * trashed, so without force it is refused. One of another post is not
     * this template's to delete.
     */
    #[Route(Method::Delete, '/wp/v2/{base:templates|template-parts}/{parent:' . self::ID . '}/revisions/{id:[\d]+}', policy: new Policy(Access::Public), args: [RevisionArgs::TEMPLATE_PARENT, RevisionArgs::ID, RevisionArgs::FORCE])]
    public function deleteRevision(Request $request, string $base, string $parent, string $id): Response
    {
        $template = $this->saved($base, $parent);
        $this->caller->require('rest_cannot_delete', self::DELETE_REFUSAL);
        if (!$this->caller->can('delete_post', $template->wpId)) {
            throw new RestError('rest_cannot_delete', self::DELETE_REFUSAL, 403);
        }
        // Whoever may delete the template may delete its revisions: unlike a post's, the reference lets an administrator (probe of this route).
        $row = $this->revisions->one((int) $id) ?? throw new RestError('rest_post_invalid_id', 'Invalid revision ID.', 404);
        if (!$request->flagged('force')) {
            throw new RestError('rest_trash_not_supported', "Revisions do not support trashing. Set 'force=true' to delete.", 501);
        }
        $revision = RevisionCollection::ofParent($row, $template->wpId);
        $previous = array_diff_key($this->shaped($base, $template, $revision, Context::of($request), 'revisions'), ['_links' => true]);
        $deleted = (new PostEvents())->deleteForGood($revision->id);
        Runtime::hooks()->action('rest_delete_revision', [$deleted, $request]);
        if (!$deleted) {
            throw new RestError('rest_cannot_delete', 'The post cannot be deleted.', 500);
        }
        return Reply::item(['deleted' => true, 'previous' => $previous], Fields::fromQuery($request->query));
    }

    /** The autosaves of a saved template, every author's, newest first. */
    #[Route(Method::Get, '/wp/v2/{base:templates|template-parts}/{id:' . self::ID . '}/autosaves', policy: new Policy(Access::Public), args: [RevisionArgs::TEMPLATE_ID, Args::CONTEXT])]
    public function autosaves(Request $request, string $base, string $id): Response
    {
        $template = $this->readable($base, $id, self::AUTOSAVES_REFUSAL);
        $context = Context::of($request);
        $fields = Fields::fromQuery($request->query);
        $objects = array_map(fn (PostRecord $row): array => $this->shaped($base, $template, $row, $context, 'autosaves'), PostRecord::fromRows($this->revisions->autosavesOf($template->wpId)));
        // A list with no paging headers: each item takes the _fields on its own.
        return Reply::item($fields === null ? $objects : array_map(static fn (array $object): array => $fields->apply($object), $objects), null);
    }

    /** The template's newest autosave, whoever saved it and whichever autosave id is asked for. */
    #[Route(Method::Get, '/wp/v2/{base:templates|template-parts}/{parent:' . self::ID . '}/autosaves/{id:[\d]+}', policy: new Policy(Access::Public), args: [RevisionArgs::TEMPLATE_PARENT, RevisionArgs::AUTOSAVE_ID, Args::CONTEXT])]
    public function autosave(Request $request, string $base, string $parent, string $id): Response
    {
        $template = $this->readable($base, $parent, self::READ_REFUSAL);
        $newest = PostRecord::fromRows($this->revisions->autosavesOf($template->wpId))[0] ?? throw new RestError('rest_post_no_autosave', 'There is no autosave revision for this post.', 404);
        return Reply::item($this->shaped($base, $template, $newest, Context::of($request), 'autosaves'), Fields::fromQuery($request->query));
    }

    /**
     * An autosave of a saved template (probe rest-template-autosave): the
     * caller's own draft is saved in place, and published; any other keeps
     * one autosave per author, holding only what the body sends (a field it
     * leaves out is stored empty). The caller is judged before the template.
     * The reply is what was saved, shown as the template, in the edit context.
     */
    #[Route(Method::Post, '/wp/v2/{base:templates|template-parts}/{id:' . self::ID . '}/autosaves', policy: new Policy(Access::Cap, 'edit_posts', caps: ['edit_theme_options'], signIn: 'rest_cannot_manage_templates', signInMessage: self::MANAGE_REFUSAL, refuse: 'rest_cannot_manage_templates', message: self::MANAGE_REFUSAL))]
    public function createAutosave(Request $request, string $base, string $id): Response
    {
        $template = $this->saved($base, $id);
        $body = $request->json();
        $text = static fn (mixed $value): ?string => is_array($value) ? (is_string($value['raw'] ?? null) ? $value['raw'] : null) : (is_string($value) ? $value : null);
        $fields = ['post_title' => $text($body['title'] ?? null), 'post_content' => $text($body['content'] ?? null), 'post_excerpt' => $text($body['description'] ?? null)];
        $post = Records::post($template->wpId);
        $userId = $this->caller->id();
        if (!$post instanceof Post) {
            throw new RestError('rest_post_invalid_parent', 'Invalid template parent ID.', 404);
        }
        if (Autosaves::inPlace($post, $userId)) {
            \defined('DOING_AUTOSAVE') || \define('DOING_AUTOSAVE', true);
            $saved = PostWrites::update(Slashes::add(['ID' => $post->ID, 'post_status' => 'publish'] + array_filter($fields, is_string(...))), true);
            if ($saved instanceof Errors) {
                throw new RestError($saved->get_error_code(), $saved->get_error_message(), 500);
            }
            $object = $this->shaped($base, $template, PostRecord::fromPost(Records::post($post->ID)), Context::Edit, 'autosaves');
            // The template itself was saved: it stands as its own autosave, with no parent.
            $object['parent'] = 0;
            return Reply::item($object, Fields::fromQuery($request->query));
        }
        // A field the body leaves out keeps the caller's autosave's value, or is empty when there is none yet.
        $own = array_values(array_filter(PostRecord::fromRows($this->revisions->autosavesOf($post->ID)), static fn (PostRecord $row): bool => $row->authorId === $userId))[0] ?? null;
        $kept = ['post_title' => $own?->title ?? '', 'post_content' => $own?->content ?? '', 'post_excerpt' => $own?->excerpt ?? ''];
        $merged = [];
        foreach ($fields as $column => $value) {
            $merged[$column] = $value ?? $kept[$column];
        }
        $saved = Autosaves::saveSlot($post, $userId, $merged);
        if ($saved instanceof Errors) {
            throw new RestError($saved->get_error_code(), $saved->get_error_message(), 500);
        }
        return Reply::item($this->shaped($base, $template, PostRecord::fromPost(Records::post($saved)), Context::Edit, 'autosaves'), Fields::fromQuery($request->query));
    }

    /** The saved template, then the caller judged: signed in, and able to edit it. */
    private function readable(string $base, string $id, string $refusal): TemplateRecord
    {
        $template = $this->saved($base, $id);
        $this->caller->require('rest_cannot_read', $refusal);
        if (!$this->caller->can('edit_post', $template->wpId)) {
            throw new RestError('rest_cannot_read', $refusal, 403);
        }
        return $template;
    }

    /** The template the id names, refused when there is none or when only a file (or a plugin) provides it. */
    private function saved(string $base, string $id): TemplateRecord
    {
        $template = $this->index->find($base === 'template-parts' ? TemplateIndex::PART : TemplateIndex::TEMPLATE, rawurldecode($id));
        if ($template === null) {
            throw new RestError('rest_post_invalid_parent', 'Invalid template parent ID.', 404);
        }
        if ($template->wpId === 0) {
            throw new RestError('rest_invalid_template', "Templates based on theme files can't have revisions.", 400);
        }
        return $template;
    }

    /**
     * A revision row shown as the template it held: the template's own
     * shape with the revision's content, title, description, author and
     * dates, its parent's id, and links to itself and its template. The
     * embed view has no dates and an empty content.
     *
     * @return array<string, mixed>
     */
    private function shaped(string $base, TemplateRecord $template, PostRecord $revision, Context $context, string $kind): array
    {
        $record = new TemplateRecord(
            theme: $template->theme,
            slug: $template->slug,
            type: $template->type,
            content: $revision->content,
            title: $revision->title,
            description: $revision->excerpt,
            source: $template->source,
            origin: $template->origin,
            hasThemeFile: $template->hasThemeFile,
            author: $revision->authorId,
            modified: str_replace(' ', 'T', $revision->modified),
            date: str_replace(' ', 'T', $revision->date),
            wpId: $revision->id,
            status: $revision->status,
            area: $template->area,
            plugin: $template->plugin,
        );
        $object = array_diff_key($this->object->view($record, $context), ['_links' => true]);
        if ($context === Context::Embed) {
            $object['content'] = [];
            unset($object['modified'], $object['date']);
        }
        $object['parent'] = $template->wpId;
        $home = $this->url->to("/wp/v2/{$base}/{$template->id()}");
        $allow = $kind === 'revisions' && $this->caller->can('delete_post', $template->wpId) ? ['GET', 'DELETE'] : ['GET'];
        $object['_links'] = [
            'self' => [Links::item('self', "{$home}/{$kind}/{$revision->id}", ['targetHints' => ['allow' => $allow]])],
            'parent' => [Links::item('parent', $home, [])],
        ];
        return $object;
    }
}
