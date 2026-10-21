<?php

declare(strict_types=1);

namespace Minn\Runtime;

use Minn\Auth\Can;
use Minn\Content\RowCache;
use Minn\I18n\Gettext;
use Minn\I18n\Numbers;
use Minn\Front\TermLists;
use Minn\Objects\Term;
use Minn\Support\Escape;
use Minn\Support\Lists;
use Minn\Support\Sprintf;
use Minn\Support\Text;

/**
 * What WordPress's template tags tell about a post's terms: its terms of a
 * taxonomy (as a list primed them, else read), its categories with their
 * old field names, its tags, its format, and a category's address and the
 * chain of its parents.
 */
final class TermTags
{
    /** A category's old field names, each with the term field it mirrors, in the order they are added. */
    private const CATEGORY_FIELDS = ['cat_ID' => 'term_id', 'category_count' => 'count', 'category_description' => 'description', 'cat_name' => 'name', 'category_nicename' => 'slug', 'category_parent' => 'parent'];

    /**
     * The terms of a post that a list primed or the object cache holds
     * (get_object_term_cache), each through get_term; false when nothing
     * holds them.
     */
    public static function cached(mixed $id, mixed $taxonomy): mixed
    {
        // What update_object_term_cache (or a plugin) left in {taxonomy}_relationships first, then a list's primed rows (probe taxonomy-blocks-user-doubts).
        $cached = Handoff::cacheGet((int) $id, "{$taxonomy}_relationships");
        $primed = $cached === false ? RowCache::peek('post_terms', (int) $id . ':' . $taxonomy) : null;
        if ($cached === false && !is_array($primed)) {
            return false;
        }
        $ids = $cached !== false
            ? array_map(static fn (mixed $term): int => (int) (is_object($term) ? $term->term_id : $term), (array) $cached)
            : array_map(static fn (array $row): int => (int) $row[0], $primed);
        // As the reference answers from its cache: the terms' meta queued to load together, each term read through get_term.
        if ($ids !== []) {
            Handoff::lazyloadTermMeta($ids);
        }
        $terms = [];
        foreach ($ids as $termId) {
            $term = Records::term($termId, (string) $taxonomy);
            if ($term instanceof Term) {
                $terms[] = $term;
            }
        }
        return $terms;
    }

    /**
     * The post formats' names (get_post_format_strings), in the site's
     * language, by slug.
     *
     * @return array<string, string>
     */
    public static function formatStrings(): array
    {
        $names = [];
        foreach (['standard' => 'Standard', 'aside' => 'Aside', 'chat' => 'Chat', 'gallery' => 'Gallery', 'link' => 'Link', 'image' => 'Image', 'quote' => 'Quote', 'status' => 'Status', 'video' => 'Video', 'audio' => 'Audio'] as $slug => $name) {
            $names[$slug] = Gettext::inContext($name, 'Post format');
        }
        return $names;
    }

    /** A post's terms of a taxonomy (get_the_terms), as get_the_terms leaves them; false for none or no post. */
    public static function ofPost(mixed $post, mixed $taxonomy): mixed
    {
        $post = Records::post($post);
        if ($post === null) {
            return false;
        }
        $terms = self::cached($post->ID, $taxonomy);
        if ($terms === false) {
            $terms = TermLookup::objectTerms($post->ID, $taxonomy);
            // Kept, as the reference keeps them, so the post's next read of these terms is answered.
            if (is_array($terms)) {
                RowCache::put('post_terms', (int) $post->ID . ':' . $taxonomy, array_values(array_map(static fn (mixed $term): array => [(int) $term->term_id], array_filter($terms, 'is_object'))));
            }
        }
        if (Records::isError($terms)) {
            return $terms;
        }
        $terms = Runtime::hooks()->filter('get_the_terms', [$terms, $post->ID, $taxonomy]);
        return $terms === [] ? false : $terms;
    }

    /** A post's categories (get_the_category), each with its old field names, as get_the_categories leaves them. */
    public static function categories(mixed $postId = false): mixed
    {
        $terms = self::ofPost($postId, 'category');
        if (!is_array($terms)) {
            $terms = [];
        }
        foreach ($terms as $term) {
            self::categoryFields($term);
        }
        return Runtime::hooks()->filter('get_the_categories', [$terms, $postId]);
    }

    /** A post's tags (get_the_tags), as get_the_tags leaves them. */
    public static function tags(mixed $post = 0): mixed
    {
        return Runtime::hooks()->filter('get_the_tags', [self::ofPost($post, 'post_tag')]);
    }

    /** A post's format (get_post_format): its post_format term's slug without the prefix; false for none or a type without formats. */
    public static function format(mixed $post = null): mixed
    {
        $post = Records::post($post);
        if ($post === null || !Registered::supports($post->post_type, 'post-formats')) {
            return false;
        }
        $format = self::ofPost($post->ID, 'post_format');
        if (empty($format)) {
            return false;
        }
        $format = array_shift($format);
        return str_replace('post-format-', '', $format->slug);
    }

    /** A category's old field names beside its term fields (_make_cat_compat), on a term object or a term array alike. */
    public static function categoryFields(mixed &$category): void
    {
        if ($category instanceof Term) {
            foreach (self::CATEGORY_FIELDS as $old => $field) {
                $category->{$old} = $category->{$field};
            }
        } elseif (is_array($category) && isset($category['term_id'])) {
            // An array's old names are references, so a later change to either name shows in both.
            foreach (self::CATEGORY_FIELDS as $old => $field) {
                $category[$old] = &$category[$field];
            }
        }
    }

    /** A category's address (get_category_link), empty for none. */
    public static function categoryLink(mixed $category): mixed
    {
        $term = $category instanceof Term ? $category : Records::term((int) $category, 'category');
        return $term instanceof Term ? TermLookup::link($term) : '';
    }

    /**
     * A category's parents and itself, outermost first (get_category_parents),
     * as names or slugs, linked or not, with the separator after each; the
     * error for an unknown category.
     */
    public static function categoryParents(mixed $categoryId, mixed $link = false, mixed $separator = '/', mixed $nicename = false, mixed $deprecated = []): mixed
    {
        $term = Records::term((int) $categoryId, 'category');
        if (!$term || Records::isError($term)) {
            return Records::isError($term) ? $term : '';
        }
        $ids = array_reverse(array_map('intval', (array) TermLookup::ancestors((int) $categoryId, 'category', 'taxonomy')));
        $ids[] = (int) $categoryId;
        $line = [];
        foreach ($ids as $id) {
            $row = Records::term($id, 'category');
            if (!$row || Records::isError($row)) {
                continue;
            }
            $line[] = ['name' => $row->name, 'slug' => $row->slug, 'link' => self::categoryLink($id)];
        }
        return TermLists::parentChain($line, (bool) $link, (string) $separator, (bool) $nicename);
    }

    /**
     * Each taxonomy's term ids of the objects not cached yet, kept in
     * {taxonomy}_relationships (update_object_term_cache); an object cached
     * there keeps its entry, and one without terms gets an empty list.
     */
    public static function cacheObjects(mixed $objectIds, mixed $objectType): void
    {
        $ids = array_values(array_filter(array_map('intval', is_array($objectIds) ? $objectIds : explode(',', (string) $objectIds))));
        foreach (Registered::objectTaxonomies($objectType) as $taxonomy) {
            $missing = array_values(array_filter($ids, static fn (int $id): bool => Handoff::cacheGet($id, "{$taxonomy}_relationships") === false));
            if ($missing === []) {
                continue;
            }
            $found = TermLookup::objectTerms($missing, $taxonomy, ['fields' => 'all_with_object_id', 'orderby' => 'name', 'update_term_meta_cache' => false]);
            $byObject = [];
            foreach (is_array($found) ? $found : [] as $term) {
                $byObject[(int) $term->object_id][] = (int) $term->term_id;
            }
            foreach ($missing as $id) {
                Handoff::cacheSet($id, $byObject[$id] ?? [], "{$taxonomy}_relationships");
            }
        }
    }

    /**
     * The objects' term caches let go (clean_object_term_cache): each of the
     * type's taxonomies' relationships, the terms' last_changed moved, then
     * plugins told; nothing at all while cache invalidation is suspended.
     */
    public static function cleanObjects(mixed $objectIds, mixed $objectType): void
    {
        if (!empty($GLOBALS['_wp_suspend_cache_invalidation'])) {
            return;
        }
        $ids = (array) $objectIds;
        foreach (Registered::objectTaxonomies($objectType) as $taxonomy) {
            foreach ($ids as $id) {
                Handoff::cacheDelete((int) $id, "{$taxonomy}_relationships");
            }
        }
        RowCache::forget('post_terms');
        Handoff::cacheSet('last_changed', microtime(), 'terms');
        Runtime::hooks()->action('clean_object_term_cache', [$ids, $objectType]);
    }

    /**
     * The taxonomies an attachment carries (get_attachment_taxonomies): those
     * registered for attachments, for its file's extension, its MIME type,
     * and each half of it (attachment:image, attachment:image/png...).
     */
    public static function attachmentTaxonomies(mixed $attachment, mixed $output = 'names'): array
    {
        $attachment = is_int($attachment) ? Records::post($attachment) : (is_array($attachment) ? (object) $attachment : $attachment);
        if (!is_object($attachment)) {
            return [];
        }
        $file = Text::basename((string) Attachments::file($attachment->ID ?? 0));
        $objects = ['attachment'];
        if (str_contains($file, '.')) {
            $objects[] = 'attachment:' . substr($file, strrpos($file, '.') + 1);
        }
        $mime = (string) ($attachment->post_mime_type ?? '');
        if ($mime !== '') {
            $objects[] = 'attachment:' . $mime;
            foreach (array_filter(explode('/', $mime)) as $half) {
                $objects[] = "attachment:{$half}";
            }
        }
        $taxonomies = [];
        foreach ($objects as $object) {
            $taxonomies = array_merge($taxonomies, Registered::objectTaxonomies($object, $output));
        }
        return $output === 'names' ? array_values(array_unique($taxonomies)) : $taxonomies;
    }

    /**
     * A post's taxonomies as sentences of term links (get_the_taxonomies):
     * a taxonomy's own template and term_template win over the ones given;
     * the terms list through wp_sprintf's %l; an attachment's file types
     * count; a taxonomy without terms is left out.
     *
     * @return array<string, string>
     */
    public static function taxonomies(mixed $post = 0, mixed $args = []): array
    {
        $post = Records::post($post);
        if ($post === null) {
            return [];
        }
        $args = Lists::args($args, ['template' => Gettext::text('%s: %l.'), 'term_template' => '<a href="%1$s">%2$s</a>']);
        $taxonomies = $post->post_type === 'attachment' ? self::attachmentTaxonomies($post) : Registered::objectTaxonomies($post->post_type);
        $out = [];
        foreach ($taxonomies as $taxonomy) {
            $object = Registered::taxonomy($taxonomy);
            $terms = self::cached($post->ID, $taxonomy);
            $terms = $terms === false ? TermLookup::objectTerms($post->ID, $taxonomy) : $terms;
            if (!$object || !is_array($terms) || $terms === []) {
                continue;
            }
            $links = [];
            foreach ($terms as $term) {
                $links[] = sprintf((string) ($object->term_template ?? $args['term_template']), Escape::attr((string) TermLookup::link($term)), $term->name);
            }
            $out[$taxonomy] = Sprintf::format((string) ($object->template ?? $args['template']), [$object->labels->name ?? $taxonomy, $links], static fn (string $fragment, mixed $value): mixed => Runtime::hooks()->filter('wp_sprintf', [$fragment, $value]));
        }
        return $out;
    }

    /**
     * Whether an object has one of the terms (is_object_in_term): an integer
     * is an id, a digit string an id, a name or a slug, any other string a
     * name or a slug; no terms asked means any term of the taxonomy. An
     * error for no object (0) or an unknown taxonomy.
     */
    public static function objectInTerm(mixed $objectId, mixed $taxonomy, mixed $terms = null): mixed
    {
        $objectId = (int) $objectId;
        if ($objectId === 0) {
            return Handoff::newError('invalid_object', Gettext::text('Invalid object ID.'));
        }
        if (!Registered::taxonomyExists($taxonomy)) {
            return Handoff::newError('invalid_taxonomy', Gettext::text('Invalid taxonomy.'));
        }
        $held = TermLookup::objectTerms([$objectId], [(string) $taxonomy]);
        if (Records::isError($held)) {
            return $held;
        }
        if (empty($terms)) {
            return $held !== [];
        }
        foreach ((array) $terms as $wanted) {
            foreach ($held as $term) {
                $id = (int) (is_object($term) ? $term->term_id : $term);
                $names = is_object($term) ? [(string) $term->slug, (string) $term->name] : [];
                if ((is_int($wanted) && $wanted === $id) || (is_string($wanted) && (in_array($wanted, $names, true) || (ctype_digit($wanted) && (int) $wanted === $id)))) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * The tag cloud's count in words for a tag's label: topic_count_text (a
     * nooped plural) first, then the text callback (default_topic_count_text
     * counts as none), then the old single_text and multiple_text, else
     * "%s item" / "%s items" (probe taxonomy-blocks-user-doubts).
     */
    public static function cloudCountText(int $count, mixed $tag, array $args): string
    {
        $noop = $args['topic_count_text'] ?? null;
        $callback = $args['topic_count_text_callback'] ?? null;
        if (!is_array($noop) && is_callable($callback) && $callback !== 'default_topic_count_text') {
            return (string) call_user_func($callback, $count, $tag, $args);
        }
        if (!is_array($noop)) {
            $noop = isset($args['single_text'], $args['multiple_text']) ? ['singular' => (string) $args['single_text'], 'plural' => (string) $args['multiple_text']] : ['singular' => '%s item', 'plural' => '%s items'];
        }
        $domain = (string) ($noop['domain'] ?? 'default');
        $context = $noop['context'] ?? null;
        $form = $context ? Gettext::pluralInContext((string) $noop['singular'], (string) $noop['plural'], $count, (string) $context, $domain) : Gettext::plural((string) $noop['singular'], (string) $noop['plural'], $count, $domain);
        return sprintf($form, Numbers::format($count));
    }

    /**
     * The category list's "all" link for a taxonomy: the archive of its
     * first type when none of its types is a post, else the posts page or
     * the home page (probe taxonomy-blocks-user-doubts).
     */
    public static function allLink(string $taxonomy): string
    {
        $types = array_map('strval', (array) (Registered::taxonomy($taxonomy)->object_type ?? []));
        if ($types !== [] && !in_array('post', $types, true)) {
            $archive = ArchiveLinks::archive($types[0]);
            if (is_string($archive)) {
                return $archive;
            }
        }
        $options = Runtime::options();
        $page = $options->filtered('page_for_posts');
        return $options->filtered('show_on_front') === 'page' && $page ? (string) PostLinks::permalink($page) : SiteUrls::home('/');
    }

    /**
     * A term's field for the core/term-data binding
     * (_block_bindings_term_data_get_value): a navigation link or submenu by
     * its own id and type ("tag" is post_tag), any other block by the termId
     * and taxonomy in its context; id, count and parent as text, the name,
     * slug, description or link. Null for no term, or a term of a hidden
     * taxonomy to someone who may not edit it (probe taxonomy-blocks-user-doubts).
     */
    public static function bindingValue(array $sourceArgs, mixed $block): mixed
    {
        $navigation = in_array($block->name ?? '', ['core/navigation-link', 'core/navigation-submenu'], true) && !empty($block->attributes['id']);
        $id = $navigation ? $block->attributes['id'] : ($block->context['termId'] ?? 0);
        $taxonomy = (string) ($navigation ? ($block->attributes['type'] ?? '') : ($block->context['taxonomy'] ?? ''));
        $term = empty($id) ? null : Records::term((int) $id, $navigation && $taxonomy === 'tag' ? 'post_tag' : $taxonomy);
        if (!$term instanceof Term || (!Registered::viewableTaxonomy($term->taxonomy) && !Can::current('edit_term', $term->term_id))) {
            return null;
        }
        return match ($sourceArgs['field'] ?? '') {
            'id' => (string) $term->term_id,
            'count' => (string) $term->count,
            'parent' => (string) $term->parent,
            'name' => $term->name,
            'slug' => $term->slug,
            'description' => $term->description,
            'link' => TermLookup::link($term),
            default => null,
        };
    }
}
