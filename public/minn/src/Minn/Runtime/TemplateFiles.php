<?php

declare(strict_types=1);

namespace Minn\Runtime;

use Minn\Blocks\Serializer;
use Minn\Content\Site;
use Minn\Db;
use Minn\I18n\Gettext;
use Minn\Json;
use Minn\Theme\TemplateHierarchy;
use Minn\Theme\TemplateIndex;
use Minn\Theme\Theme;

/**
 * The theme templates WordPress's template functions name: the index and
 * embed templates found through the hierarchy, the slugs a block template
 * stands for, the template types and part areas core knows, and a block
 * template made from the theme's file or from a saved post, as the
 * reference makes one (probe template-part-lookup): the theme's names and
 * folders read on the way, the area a theme declares checked against the
 * areas core knows, and the markup passed through the block hooks.
 */
final class TemplateFiles
{
    /** Template types named without a context. */
    private const PLAIN_TITLES = ['attachment', 'privacy-policy'];

    /** The prefix of the post format archives' template slugs. */
    private const FORMAT_SLUG = 'taxonomy-post_format-post-format-';
    /** The theme's index template (get_index_template). */
    public static function index(): mixed
    {
        return Handoff::queryTemplate('index');
    }

    /**
     * The template an embed is shown with (get_embed_template): embed-{type}-{format}.php,
     * embed-{type}.php, then embed.php.
     */
    public static function embed(): mixed
    {
        $object = MainQuery::current()->get_queried_object();
        $type = empty($object->post_type) ? null : $object->post_type;
        $format = $type === null ? false : TermTags::format($object);
        $names = array_filter([$format ? "embed-{$type}-{$format}" : null, $type === null ? null : "embed-{$type}", 'embed'], static fn (?string $name): bool => $name !== null);
        return Handoff::queryTemplate('embed', array_values(array_map(static fn (string $name): string => "{$name}.php", $names)));
    }

    /**
     * The template slugs a slug stands for, most specific first
     * (get_template_hierarchy).
     *
     * @return list<string>
     */
    public static function hierarchy(mixed $slug, mixed $isCustom = false, mixed $templatePrefix = ''): array
    {
        return TemplateHierarchy::for((string) $slug, (bool) $isCustom, (string) $templatePrefix, array_values(Registered::postTypes()), array_values(Registered::taxonomies()));
    }

    /**
     * The template types core knows (get_default_block_template_types), as
     * default_template_types leaves them: each named and described in the
     * site's language, then one per post format.
     */
    public static function defaultTypes(): mixed
    {
        $types = [];
        foreach ((array) Json::file(MINN_ENGINE_DIR . '/data/template-types.json') as $slug => $type) {
            if (str_starts_with((string) $slug, self::FORMAT_SLUG)) {
                continue;
            }
            $title = (string) ($type['title'] ?? '');
            $types[$slug] = [
                'title' => in_array($slug, self::PLAIN_TITLES, true) ? Gettext::text($title) : Gettext::inContext($title, 'Template name'),
                'description' => Gettext::text((string) ($type['description'] ?? '')),
            ];
        }
        foreach (TermTags::formatStrings() as $format => $name) {
            $types[self::FORMAT_SLUG . $format] = [
                'title' => sprintf(Gettext::inContext('Post Format: %s', 'Template name'), $name),
                'description' => sprintf(Gettext::text('Displays the %s post format archive.'), $name),
            ];
        }
        return Runtime::hooks()->filter('default_template_types', [$types]);
    }

    /** The template part areas core knows (get_allowed_block_template_part_areas), each named in the site's language, as default_wp_template_part_areas leaves them. */
    public static function partAreas(): mixed
    {
        $areas = [];
        foreach ((array) Json::file(MINN_ENGINE_DIR . '/data/template-part-areas.json') as $area) {
            $area['label'] = Gettext::inContext((string) $area['label'], 'template part area');
            $area['description'] = Gettext::text((string) $area['description']);
            $areas[] = $area;
        }
        return Runtime::hooks()->filter('default_wp_template_part_areas', [$areas]);
    }

    /**
     * A template part's area as the areas core knows it
     * (_filter_block_template_part_area): one it does not know becomes
     * uncategorized, with a notice.
     */
    public static function partArea(mixed $type): mixed
    {
        if (in_array($type, array_column((array) self::partAreas(), 'area'), true)) {
            return $type;
        }
        $raised = Notices::message('_filter_block_template_part_area', sprintf(Gettext::text('"%1$s" is not a supported wp_template_part area value and has been added as "%2$s".'), $type, 'uncategorized'), E_USER_NOTICE);
        if ($raised !== null) {
            trigger_error($raised, E_USER_NOTICE);
        }
        return 'uncategorized';
    }

    /** The active theme's templates, saved and in its files; null with no block theme booted. */
    public static function templateIndex(): ?TemplateIndex
    {
        $theme = Runtime::current()->get('theme');
        if (!$theme instanceof Theme) {
            return null;
        }
        $db = Db::shared();
        return new TemplateIndex($db, $theme, new Site($db), Runtime::blockTemplates());
    }

    /**
     * One theme template file, described (_get_block_template_file), after
     * the theme's names and folders are read: its slug, path, theme and type;
     * a part's area, the one theme.json declares checked against the areas
     * core knows (_filter_block_template_part_area); a custom template's
     * title and post types. Null for no such file, or a type that is neither.
     *
     * @return array<string, mixed>|null
     */
    public static function file(mixed $type, mixed $slug): ?array
    {
        if ($type !== 'wp_template' && $type !== 'wp_template_part') {
            return null;
        }
        Themes::stylesheet();
        Themes::stylesheetDirectory();
        Themes::template();
        Themes::templateDirectory();
        $info = self::templateIndex()?->fileInfo((string) $type, (string) $slug);
        if ($info === null) {
            return null;
        }
        Themes::stylesheet();
        $declared = $type === 'wp_template_part' ? Runtime::current()->get('theme')?->declaredPartArea((string) $slug) : null;
        if ($declared !== null) {
            $info['area'] = self::partArea($declared);
        }
        return $info;
    }

    /**
     * A template built from a file as file() describes one
     * (_build_block_template_result_from_file): the default template types'
     * title and description for a template they name (which is not custom),
     * a part's area, a custom template's post types, and the markup through
     * the block hooks, the theme named on every template part in it.
     *
     * @param array<string, mixed>|object $file
     */
    public static function fromFile(mixed $file, mixed $type): object
    {
        $file = (array) $file;
        $types = (array) self::defaultTypes();
        $known = $type === 'wp_template' ? ($types[$file['slug']] ?? null) : null;
        Themes::stylesheet();
        $template = Handoff::newBlockTemplate();
        $template->id = $file['theme'] . '//' . $file['slug'];
        $template->theme = $file['theme'];
        $template->slug = $file['slug'];
        $template->source = 'theme';
        $template->type = $type;
        $template->title = $known['title'] ?? ($file['title'] ?? $file['slug']);
        $template->description = $known['description'] ?? '';
        $template->status = 'publish';
        $template->has_theme_file = true;
        $template->is_custom = $known === null;
        $template->post_types = $file['postTypes'] ?? null;
        $template->area = $type === 'wp_template_part' ? ($file['area'] ?? null) : null;
        $markup = (string) file_get_contents((string) $file['path']);
        $template->content = $type === 'wp_template_part' ? self::hookedPart($markup, $template, []) : (string) Handoff::blockHooks($markup, $template, 'insert_hooked_blocks_and_set_ignored_hooked_blocks_metadata');
        return $template;
    }

    /**
     * A theme file's template by id, past whatever the site saved over it
     * (get_block_file_template): pre_get_block_file_template may answer
     * first; only the active theme's files are found; the template, or null,
     * through get_block_file_template.
     */
    public static function fileTemplate(mixed $id, mixed $type = 'wp_template'): mixed
    {
        $template = Runtime::hooks()->filter('pre_get_block_file_template', [null, $id, $type]);
        if ($template !== null) {
            return $template;
        }
        $parts = explode('//', (string) $id, 2);
        $file = count($parts) === 2 && $parts[0] === Themes::stylesheet() ? self::file($type, $parts[1]) : null;
        $template = $file === null ? null : self::fromFile($file, $type);
        return Runtime::hooks()->filter('get_block_file_template', [$template, $id, $type]);
    }

    /**
     * A saved template or part as a template (_build_block_template_result_from_post),
     * under the theme its wp_theme term names; an error when it names none.
     * A part's area is its wp_template_part_area term's, none without one.
     * Its origin and suggestion meta and whether the theme has the file are
     * read as the reference reads them; its markup goes through the block
     * hooks, a part's with the hooked blocks its post says to leave out.
     */
    public static function fromPost(mixed $post): mixed
    {
        $post = Records::post($post);
        $terms = $post === null ? false : TermTags::ofPost($post, 'wp_theme');
        if (Records::isError($terms)) {
            return $terms;
        }
        $index = self::templateIndex();
        if ($post === null || !is_array($terms) || $terms === [] || $index === null) {
            return Handoff::newError('template_missing_theme', Gettext::text('No theme is defined for this template.'));
        }
        $part = $post->post_type === 'wp_template_part';
        $areas = $part ? TermTags::ofPost($post, 'wp_template_part_area') : false;
        MetaData::get('post', $post->ID, 'origin', true);
        MetaData::get('post', $post->ID, 'is_wp_suggestion', true);
        self::defaultTypes();
        self::file($post->post_type, $post->post_name);
        Themes::stylesheet();
        $template = \_minn_block_template_from($index->postRecord(get_object_vars($post)), $index);
        $template->theme = $terms[0]->name;
        $template->id = $template->theme . '//' . $post->post_name;
        if (!$part) {
            $template->content = (string) Handoff::blockHooks((string) $template->content, $template, 'insert_hooked_blocks_and_set_ignored_hooked_blocks_metadata');
            return $template;
        }
        $template->area = is_array($areas) && $areas !== [] ? $areas[0]->name : null;
        $ignored = Json::decode((string) MetaData::get('post', $post->ID, '_wp_ignored_hooked_blocks', true), or: null);
        $template->content = self::hookedPart((string) $template->content, $template, is_array($ignored) ? $ignored : []);
        return $template;
    }

    /**
     * A part's markup through the block hooks, as the reference runs them:
     * wrapped in a template-part block (so blocks hooked as its first or
     * last child are placed), that block marked with the hooked blocks to
     * leave out, then unwrapped.
     *
     * @param list<string> $ignored
     */
    private static function hookedPart(string $markup, object $template, array $ignored): string
    {
        $attributes = $ignored === [] ? '' : Serializer::attributes(['metadata' => ['ignoredHookedBlocks' => $ignored]]) . ' ';
        $wrapped = $markup === '' ? "<!-- wp:template-part {$attributes}/-->" : "<!-- wp:template-part {$attributes}-->{$markup}<!-- /wp:template-part -->";
        $hooked = (string) Handoff::blockHooks($wrapped, $template, 'insert_hooked_blocks_and_set_ignored_hooked_blocks_metadata');
        $start = (int) strpos($hooked, '-->') + 3;
        return substr($hooked, $start, (int) strrpos($hooked, '<!--') - $start);
    }
}
