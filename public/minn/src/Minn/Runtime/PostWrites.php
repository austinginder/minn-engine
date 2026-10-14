<?php

declare(strict_types=1);

namespace Minn\Runtime;

use Minn\Auth\Can;
use Minn\Content\Comments;
use Minn\Content\Posts;
use Minn\Content\PostWriter;
use Minn\I18n\Gettext;
use Minn\Objects\Post;
use Minn\Support\Sanitize;
use Minn\Support\Slashes;

/**
 * Posts written as WordPress's post functions write them: a post stored
 * and changed (its columns through their filters, then its terms, meta and
 * template), published, sent to the trash and back, deleted with what
 * hangs off it, its terms and categories set, made sticky or not; each step
 * with the actions the reference fires in its order, and the cached rows
 * let go.
 */
final class PostWrites
{
    /**
     * A post stored or changed (wp_insert_post): its id, 0 or the error
     * when it cannot be; the revision is saved from wp_after_insert_post
     * when the after hooks fire.
     */
    public static function insert(mixed $postarr, mixed $wpError = false, mixed $fireAfterHooks = true): mixed
    {
        $given = (array) $postarr;
        $update = !empty($given['ID']);
        $existing = $update ? Records::post((int) $given['ID']) : null;
        if ($update && $existing === null) {
            return $wpError ? Handoff::newError('invalid_post', 'Invalid post ID.') : 0;
        }
        $sanitized = PostSave::sanitized($given);
        $insert = self::inserter();
        $before = $existing?->to_array();
        $columns = $insert->columns((array) Slashes::strip($sanitized), $before);
        ['post_type' => $columnType] = $columns + ['post_type' => null];
        $type = (string) ($columnType ?? $existing->post_type);
        if (PostSave::refusesEmpty($sanitized, $type)) {
            return $wpError ? Handoff::newError('empty_content', 'Content, title, and excerpt are empty.') : 0;
        }
        $postId = (int) ($existing?->ID ?? 0);
        $columns = array_replace($columns, ['post_parent' => (string) PostSave::parent($sanitized, $postId)]);
        self::freeSlug($columns, $postId);
        $columns = PostSave::guidAndSlug($insert->resolve($columns, $before), $postId, $type, self::writer()->slugs());
        $columns = array_replace($columns, PostSave::data($columns, $sanitized, $given, $postId));
        self::beforeSave($columns, $postId);
        $id = $insert->persist($columns, $existing?->ID, static fn (int $id): string => (string) PostLinks::permalink($id));
        self::slugFromId($id, $columns, $type);
        Handoff::cacheDelete($id, 'posts');
        ['post_status' => $status] = $columns + ['post_status' => null];
        $refused = self::templateRefusal($id, self::inputs($id, (array) Slashes::strip($given), $type, (string) $status, $update), (bool) $wpError);
        self::writer()->recountTaxonomiesOf($id);
        if ($refused !== null) {
            return $refused;
        }
        if ($type === 'attachment') {
            return self::attachmentSaved($id, $given, $existing);
        }
        $post = self::saved($id, $update, $existing);
        if ($fireAfterHooks) {
            self::afterInsert($post, $update, $existing);
        }
        return $id;
    }

    /**
     * A post changed (wp_update_post): the stored post under the given
     * fields, as the reference merges them; an attachment saved as one.
     */
    public static function update(mixed $postarr = [], mixed $wpError = false, mixed $fireAfterHooks = true): mixed
    {
        $postarr = is_object($postarr) ? get_object_vars($postarr) : (array) $postarr;
        $post = Records::post((int) ($postarr['ID'] ?? 0));
        if ($post === null) {
            return $wpError ? Handoff::newError('invalid_post', 'Invalid post ID.') : 0;
        }
        $merged = array_merge($post->to_array(), Slashes::strip($postarr));
        ['post_date' => $date, 'post_date_gmt' => $dateGmt] = $postarr + ['post_date' => null, 'post_date_gmt' => null];
        if ($date !== null && $dateGmt === null) {
            $merged = array_replace($merged, ['post_date_gmt' => '']);
        }
        ['post_type' => $mergedType] = $merged + ['post_type' => null];
        if ($mergedType === 'attachment') {
            return \wp_insert_attachment(Slashes::add($merged), false, 0, $wpError, $fireAfterHooks);
        }
        if (!isset($postarr['tags_input'])) {
            unset($merged['tags_input']);
        }
        return self::insert(Slashes::add($merged), $wpError, $fireAfterHooks);
    }

    /**
     * A post deleted (wp_delete_post): a post or page to the trash first
     * unless forced or the trash is off; an attachment as one; otherwise
     * unstuck, its terms, children, revisions, comments and meta taken
     * away, then the row. The post, or what pre_delete_post answered.
     */
    public static function delete(mixed $postId = 0, mixed $force = false): mixed
    {
        $post = Records::post($postId);
        if ($post === null) {
            return $post;
        }
        if (!$force && in_array($post->post_type, ['post', 'page'], true) && $post->post_status !== 'trash' && EMPTY_TRASH_DAYS) {
            return self::trash($postId);
        }
        if ($post->post_type === 'attachment') {
            return \wp_delete_attachment($postId, $force);
        }
        $hooks = Runtime::hooks();
        $check = $hooks->filter('pre_delete_post', [null, $post, $force]);
        if ($check !== null) {
            return $check;
        }
        self::unstick($post->ID);
        $hooks->action('before_delete_post', [$post->ID, $post]);
        MetaData::delete('post', $post->ID, '_wp_trash_meta_status');
        MetaData::delete('post', $post->ID, '_wp_trash_meta_time');
        TermWrites::deleteObjectRelationships($post->ID, Registered::objectTaxonomies($post->post_type));
        self::writer()->reparentChildren($post->ID, (int) $post->post_parent, $post->post_type === 'page' ? ['page', 'attachment'] : ['attachment']);
        foreach (PostRevisions::all($post->ID, ['check_enabled' => false]) as $revision) {
            PostRevisions::delete($revision);
        }
        foreach ((new Comments(Runtime::current()->db))->idsOf($post->ID) as $commentId) {
            CommentWrites::delete($commentId, true);
        }
        self::deleteMeta($post->ID);
        return self::remove($post);
    }

    /** A post to the trash (wp_trash_post): its status, time and slug kept for the way back; the post as it was, or false. */
    public static function trash(mixed $postId = 0): mixed
    {
        $post = Records::post($postId);
        if ($post === null || $post->post_status === 'trash') {
            return false;
        }
        $hooks = Runtime::hooks();
        $check = $hooks->filter('pre_trash_post', [null, $post, $post->post_status]);
        if ($check !== null) {
            return $check;
        }
        self::unstick($post->ID);
        $hooks->action('wp_trash_post', [$post->ID, $post->post_status]);
        MetaData::add('post', $post->ID, '_wp_trash_meta_status', $post->post_status);
        MetaData::add('post', $post->ID, '_wp_trash_meta_time', time());
        MetaData::add('post', $post->ID, '_wp_desired_post_slug', $post->post_name);
        PostCaches::clean($post->ID);
        self::beforeSave(['post_status' => 'trash', 'post_name' => $post->post_name . '__trashed'] + $post->to_array(), $post->ID);
        self::writer()->trash((new Posts(Runtime::current()->db))->find($post->ID));
        self::trashSaved($post);
        CommentWrites::trashPostComments($post->ID);
        $hooks->action('trashed_post', [$post->ID, $post->post_status]);
        return $post;
    }

    /**
     * A post back from the trash (wp_untrash_post): to draft unless
     * wp_untrash_post_status says otherwise (an attachment to inherit), its
     * slug given back; the post as it was, or false.
     */
    public static function untrash(mixed $postId = 0): mixed
    {
        $post = Records::post($postId);
        if ($post === null || $post->post_status !== 'trash') {
            return false;
        }
        $previous = (string) MetaData::get('post', $post->ID, '_wp_trash_meta_status', true);
        $hooks = Runtime::hooks();
        $check = $hooks->filter('pre_untrash_post', [null, $post, $previous]);
        if ($check !== null) {
            return $check;
        }
        $hooks->action('untrash_post', [$post->ID, $previous]);
        $new = $hooks->filter('wp_untrash_post_status', ['draft', $post->ID, $previous]);
        if ($post->post_type === 'attachment' && !in_array($new, ['inherit', 'private', 'trash', 'auto-draft'], true)) {
            $new = 'inherit';
        }
        MetaData::delete('post', $post->ID, '_wp_trash_meta_status');
        MetaData::delete('post', $post->ID, '_wp_trash_meta_time');
        $desired = (string) MetaData::get('post', $post->ID, '_wp_desired_post_slug', true);
        $columns = ['post_status' => $new];
        if ($desired !== '' && str_ends_with((string) $post->post_name, '__trashed')) {
            $columns = array_replace($columns, ['post_name' => self::writer()->uniqueSlug($desired, $post->ID, $post->post_type, (int) $post->post_parent)]);
        }
        MetaData::delete('post', $post->ID, '_wp_desired_post_slug');
        self::beforeSave($columns + $post->to_array(), $post->ID);
        self::writer()->update($post->ID, $columns);
        self::trashSaved($post);
        CommentWrites::untrashPostComments($post->ID);
        $hooks->action('untrashed_post', [$post->ID, $previous]);
        return $post;
    }

    /** What plugins hear once a post and all it carries are saved (wp_after_insert_post). */
    public static function afterInsert(mixed $post, mixed $update, mixed $postBefore): void
    {
        $post = Records::post($post);
        if ($post === null) {
            return;
        }
        Runtime::hooks()->action('wp_after_insert_post', [$post->ID, $post, $update, $postBefore]);
    }

    /** A post published now (wp_publish_post), a floating date settled, with the save's actions. */
    public static function publish(mixed $post): void
    {
        $before = Records::post($post);
        if ($before === null || $before->post_status === 'publish') {
            return;
        }
        $columns = ['post_status' => 'publish'];
        if ($before->post_date_gmt === '0000-00-00 00:00:00') {
            $columns = array_replace($columns, ['post_date_gmt' => SiteTime::toGmt($before->post_date)]);
        }
        self::writer()->update($before->ID, $columns);
        PostCaches::clean($before);
        $post = Records::post($before->ID);
        self::transitionStatus('publish', $before->post_status, $post);
        $hooks = Runtime::hooks();
        $hooks->action("edit_post_{$post->post_type}", [$post->ID, $post]);
        $hooks->action('edit_post', [$post->ID, $post]);
        $hooks->action("save_post_{$post->post_type}", [$post->ID, $post, true]);
        $hooks->action('save_post', [$post->ID, $post, true]);
        $hooks->action('wp_insert_post', [$post->ID, $post, true]);
        self::afterInsert($post, true, $before);
    }

    /** The publish_future_post event's work (check_and_publish_future_post): a scheduled post whose time has come goes out; one early is scheduled again. */
    public static function publishFuture(mixed $post): void
    {
        $post = Records::post($post);
        if ($post === null || $post->post_status !== 'future') {
            return;
        }
        $time = strtotime($post->post_date_gmt . ' GMT');
        if ($time > time()) {
            CronEvents::clearHook('publish_future_post', [$post->ID]);
            CronEvents::scheduleSingle($time, 'publish_future_post', [$post->ID]);
            return;
        }
        self::publish($post->ID);
    }

    /**
     * The status change announced (wp_transition_post_status). The named
     * hooks fire only for names that exist, so a stored status cannot forge
     * another action's name: {old}_to_{new} when the new status is
     * registered and the old one is too (or is "new"), {new}_{type} when the
     * new status and the post type are registered.
     */
    public static function transitionStatus(mixed $newStatus, mixed $oldStatus, mixed $post): void
    {
        $hooks = Runtime::hooks();
        $hooks->action('transition_post_status', [$newStatus, $oldStatus, $post]);
        $known = Registered::status($newStatus) !== null;
        if ($known && ($oldStatus === 'new' || Registered::status($oldStatus) !== null)) {
            $hooks->action("{$oldStatus}_to_{$newStatus}", [$post]);
        }
        if ($known && Registered::postTypeExists($post->post_type)) {
            $hooks->action("{$newStatus}_{$post->post_type}", [$post->ID, $post, $oldStatus]);
        }
    }

    /**
     * A post's categories set (wp_set_post_categories): posts, and the types
     * default_category_post_types adds, fall back to the default category.
     */
    public static function setCategories(mixed $postId = 0, mixed $categories = [], mixed $append = false): mixed
    {
        $postId = (int) $postId;
        $type = PostReads::type($postId);
        $categories = array_values(array_filter(array_map('intval', (array) $categories)));
        if ($categories === [] && !$append && in_array($type, ['post', ...(array) Runtime::hooks()->filter('default_category_post_types', [[]])], true)) {
            $categories = [(int) Runtime::options()->filtered('default_category')];
        }
        return self::setTerms($postId, $categories, 'category', $append);
    }

    /** A post's terms of a taxonomy set (wp_set_post_terms): a comma list split, a hierarchical taxonomy's terms by id. */
    public static function setTerms(mixed $postId = 0, mixed $terms = '', mixed $taxonomy = 'post_tag', mixed $append = false): mixed
    {
        $postId = (int) $postId;
        if ($postId <= 0) {
            return false;
        }
        if (empty($terms)) {
            $terms = [];
        }
        if (!is_array($terms)) {
            $terms = array_values(array_filter(array_map('trim', explode(',', str_replace('，', ',', (string) $terms))), static fn (string $t): bool => $t !== ''));
        }
        if (Registered::hierarchicalTaxonomy($taxonomy)) {
            $terms = array_values(array_unique(array_map('intval', $terms)));
        }
        return TermWrites::setObjectTerms($postId, $terms, $taxonomy, $append);
    }

    /** A post's tags set (wp_set_post_tags). */
    public static function setTags(mixed $postId = 0, mixed $tags = '', mixed $append = false): mixed
    {
        return self::setTerms($postId, $tags, 'post_tag', $append);
    }

    /** A post made sticky (stick_post), then post_stuck; nothing when it already is. */
    public static function stick(mixed $postId): void
    {
        $postId = (int) $postId;
        $stickies = self::stickies();
        if (in_array($postId, $stickies, true)) {
            return;
        }
        $stickies[] = $postId;
        OptionWrites::update('sticky_posts', $stickies);
        Runtime::hooks()->action('post_stuck', [$postId]);
    }

    /** A post no longer sticky (unstick_post), then post_unstuck; nothing when it was not. */
    public static function unstick(mixed $postId): void
    {
        $postId = (int) $postId;
        $stickies = self::stickies();
        if (!in_array($postId, $stickies, true)) {
            return;
        }
        OptionWrites::update('sticky_posts', array_values(array_diff($stickies, [$postId])));
        Runtime::hooks()->action('post_unstuck', [$postId]);
    }

    /** A slug no other post holds (wp_unique_post_slug), through the reference's slug filters; drafts, pending posts and revisions keep theirs. */
    public static function uniqueSlug(mixed $slug, mixed $postId, mixed $status, mixed $type, mixed $parent): mixed
    {
        if (PostSave::keepsSlug((string) $status, (string) $type)) {
            return $slug;
        }
        return PostSave::slugFilters((string) $slug, (int) $postId, (string) $status, (string) $type, (int) $parent, self::writer()->slugs());
    }

    /** Every trashed post holding a slug (but the one named) given the __trashed suffix, so a live post may take it (wp_add_trashed_suffix_to_post_name_for_trashed_posts). */
    public static function suffixTrashed(mixed $postName, mixed $postId = 0): void
    {
        foreach (PostReads::query(['name' => $postName, 'post_status' => 'trash', 'post_type' => 'any', 'nopaging' => true, 'post__not_in' => [(int) $postId]]) as $post) {
            self::suffixTrashedPost($post);
        }
    }

    /** A post's slug given the __trashed suffix once, the slug it wanted kept (wp_add_trashed_suffix_to_post_name_for_post); the slug it now has. */
    public static function suffixTrashedPost(mixed $post): mixed
    {
        $post = Records::post($post);
        if (str_ends_with((string) $post->post_name, '__trashed')) {
            return $post->post_name;
        }
        MetaData::add('post', $post->ID, '_wp_desired_post_slug', $post->post_name);
        $name = $post->post_name . '__trashed';
        $wpdb = $GLOBALS['wpdb'];
        $wpdb->update($wpdb->posts, ['post_name' => $name], ['ID' => $post->ID]);
        PostCaches::clean($post->ID);
        return $name;
    }

    /** A live post left without a slug (no title to make one from) takes its id, made free. @param array<string, mixed> $columns */
    private static function slugFromId(int $id, array $columns, string $type): void
    {
        ['post_name' => $name, 'post_status' => $status, 'post_title' => $title, 'post_parent' => $parent] = $columns + ['post_name' => null, 'post_status' => null, 'post_title' => null, 'post_parent' => null];
        if ((string) ($name ?? '') !== '' || PostSave::keepsSlug((string) $status, $type)) {
            return;
        }
        $slug = self::uniqueSlug(Sanitize::title((string) ($title ?? ''), (string) $id), $id, (string) $status, $type, (int) ($parent ?? 0));
        self::writer()->update($id, ['post_name' => $slug]);
    }

    /** @return list<int> the sticky posts' ids */
    private static function stickies(): array
    {
        $stickies = Runtime::options()->filtered('sticky_posts');
        return is_array($stickies) ? array_map('intval', $stickies) : [];
    }

    /** A trashed post still holding the slug a save wants gives it up first, unless add_trashed_suffix_to_trashed_posts says no. @param array<string, mixed> $columns */
    private static function freeSlug(array $columns, int $postId): void
    {
        ['post_name' => $name, 'post_status' => $status] = $columns + ['post_name' => null, 'post_status' => null];
        $wanted = (string) ($name ?? '');
        if ($wanted !== '' && ($status ?? '') !== 'trash' && Runtime::hooks()->filter('add_trashed_suffix_to_trashed_posts', [true, $wanted, $postId])) {
            self::suffixTrashed($wanted, $postId);
        }
    }

    /** What plugins hear before a post's row is written: pre_post_insert for a new post, pre_post_update for one that exists. @param array<string, mixed> $data */
    public static function beforeSave(array $data, int $postId = 0): void
    {
        if ($postId > 0) {
            Runtime::hooks()->action('pre_post_update', [$postId, $data]);
            return;
        }
        Runtime::hooks()->action('pre_post_insert', [$data]);
    }

    /**
     * A page template the theme does not offer: the post stands either way;
     * asked for an error, the save stops there with one (no save hooks),
     * otherwise the post keeps the default template.
     */
    private static function templateRefusal(int $id, mixed $kept, mixed $wpError): mixed
    {
        if ($kept) {
            return null;
        }
        if ($wpError) {
            return Handoff::newError('invalid_page_template', Gettext::text('Invalid page template.'));
        }
        MetaData::updatePost($id, '_wp_page_template', 'default');
        return null;
    }

    /**
     * The terms, meta and template a postarr carries beside the columns;
     * false when the page template is not one the theme offers.
     *
     * @param array<string, mixed> $postarr
     */
    private static function inputs(int $id, array $postarr, string $type, string $status, mixed $update): bool
    {
        $taxonomies = Registered::objectTaxonomies($type);
        $categories = PostInsert::categories($postarr, $type, $status, $update, $taxonomies, (int) Runtime::options()->filtered('default_category'));
        if ($categories !== null) {
            self::setCategories($id, $categories);
        }
        if (isset($postarr['tags_input']) && in_array('post_tag', Registered::objectTaxonomies($type), true)) {
            self::setTags($id, $postarr['tags_input']);
        }
        $input = is_array($postarr['tax_input'] ?? null) ? $postarr['tax_input'] : [];
        foreach (Registered::objectTaxonomies($type, 'objects') as $name => $object) {
            $default = empty($object->default_term) || isset($input[$name]) ? 0 : (int) Runtime::options()->filtered("default_term_{$name}");
            if ($default > 0 && TermLookup::objectTerms($id, $name, ['fields' => 'ids']) === []) {
                $input[$name] = [$default];
            }
        }
        foreach ($input as $taxonomy => $terms) {
            $object = Registered::taxonomy((string) $taxonomy);
            if (!$object || Can::current($object->cap->assign_terms)) {
                self::setTerms($id, $terms, (string) $taxonomy);
            }
        }
        foreach (is_array($postarr['meta_input'] ?? null) ? $postarr['meta_input'] : [] as $key => $value) {
            MetaData::updatePost($id, (string) $key, $value);
        }
        if (isset($postarr['page_template'])) {
            $template = (string) $postarr['page_template'];
            if ($template === '') {
                MetaData::delete('post', $id, '_wp_page_template');
            } elseif ($template === 'default' || array_key_exists($template, Themes::theme()->get_page_templates(Records::post($id)))) {
                MetaData::updatePost($id, '_wp_page_template', $template);
            } else {
                return false;
            }
        }
        return true;
    }

    /**
     * An attachment's save ends as the reference's does: its file recorded,
     * then add_attachment, or edit_attachment and attachment_updated; no
     * save_post actions, and no wp_after_insert_post whatever the caller
     * asks (a REST save fires that itself).
     *
     * @param array<string, mixed> $postarr
     */
    private static function attachmentSaved(int $id, array $postarr, ?Post $postBefore): int
    {
        if (!empty($postarr['file'])) {
            \update_attached_file($id, (string) $postarr['file']);
        }
        PostCaches::clean($postBefore ?? $id);
        $hooks = Runtime::hooks();
        if ($postBefore !== null) {
            $hooks->action('edit_attachment', [$id]);
            $hooks->action('attachment_updated', [$id, Records::post($id), $postBefore]);
        } else {
            $hooks->action('add_attachment', [$id]);
        }
        return $id;
    }

    /**
     * What plugins hear once a post's row and its own terms are written, in
     * the reference's order: the caches let go, the status transition, the
     * edit actions for an update, the save actions.
     */
    public static function saved(int $postId, mixed $update, ?Post $postBefore): ?Post
    {
        PostCaches::clean($postBefore ?? $postId);
        $post = Records::post($postId);
        if ($post === null) {
            return null;
        }
        self::transitionStatus($post->post_status, $postBefore instanceof Post ? $postBefore->post_status : 'new', $post);
        $hooks = Runtime::hooks();
        if ($update && $postBefore instanceof Post) {
            $hooks->action("edit_post_{$post->post_type}", [$post->ID, $post]);
            $hooks->action('edit_post', [$post->ID, $post]);
            $hooks->action('post_updated', [$post->ID, $post, $postBefore]);
        }
        $hooks->action("save_post_{$post->post_type}", [$post->ID, $post, $update]);
        $hooks->action('save_post', [$post->ID, $post, $update]);
        $hooks->action('wp_insert_post', [$post->ID, $post, $update]);
        return $post;
    }

    /** A trash or its undoing ends as the save it is: an attachment's, or a post's with its transition and wp_after_insert_post. */
    private static function trashSaved(Post $post): void
    {
        if (Registered::objectInTaxonomy($post->post_type, 'category')) {
            $categories = PostReads::categories($post->ID);
            $default = $categories === [] && $post->post_type === 'post' && $post->post_status !== 'auto-draft';
            self::setCategories($post->ID, $default ? [(int) Runtime::options()->filtered('default_category')] : $categories);
        }
        if ($post->post_type === 'attachment') {
            self::attachmentSaved($post->ID, [], $post);
            return;
        }
        self::afterInsert(self::saved($post->ID, true, $post), true, $post);
    }

    /** Each meta row of a post removed by its id, with the hooks of each. */
    public static function deleteMeta(int $postId): void
    {
        foreach ((new Meta(Runtime::current()->db))->rowsOf('post', $postId) as $row) {
            MetaData::deleteByMid('post', (int) $row['meta_id']);
        }
    }

    /** The row itself, between the delete actions, typed and untyped, and the caches let go after. */
    private static function remove(Post $post): Post
    {
        $hooks = Runtime::hooks();
        $hooks->action("delete_post_{$post->post_type}", [$post->ID, $post]);
        $hooks->action('delete_post', [$post->ID, $post]);
        self::writer()->destroy($post->ID);
        $hooks->action("deleted_post_{$post->post_type}", [$post->ID, $post]);
        $hooks->action('deleted_post', [$post->ID, $post]);
        PostCaches::clean($post);
        $hooks->action('after_delete_post', [$post->ID, $post]);
        return $post;
    }

    /** The insert decisions, with the site's options, the user's capabilities and the date helpers handed in. */
    private static function inserter(): PostInsert
    {
        return new PostInsert(
            self::writer(),
            (int) CurrentUser::id(),
            static fn (string $type, string $kind): string => (string) CommentChecks::defaultStatus($type, $kind),
            static fn (string $type, string $feature): bool => Registered::supports($type, $feature),
            static fn (string $type): bool => Can::current(Registered::postType($type)->cap->publish_posts ?? 'publish_posts'),
            static fn (string $date): string => SiteTime::toGmt($date),
            static fn (bool $gmt): string => (string) SiteTime::now('mysql', $gmt),
        );
    }

    private static function writer(): PostWriter
    {
        $db = Runtime::current()->db;
        return new PostWriter($db, new Posts($db), Runtime::current()->site);
    }
}
