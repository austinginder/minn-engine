<?php

declare(strict_types=1);

namespace Minn\Runtime;

use Minn\Json;
use Minn\Theme\TemplateHierarchy;

/**
 * The theme templates WordPress's template functions name: the index and
 * embed templates found through the hierarchy, the slugs a block template
 * stands for, and the template types and part areas core knows.
 */
final class TemplateFiles
{
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

    /** The template types core knows (get_default_block_template_types), as default_template_types leaves them. */
    public static function defaultTypes(): mixed
    {
        return Runtime::hooks()->filter('default_template_types', [Json::file(MINN_ENGINE_DIR . '/data/template-types.json')]);
    }

    /** The template part areas core knows (get_allowed_block_template_part_areas), as default_wp_template_part_areas leaves them. */
    public static function partAreas(): mixed
    {
        return Runtime::hooks()->filter('default_wp_template_part_areas', [Json::file(MINN_ENGINE_DIR . '/data/template-part-areas.json')]);
    }
}
