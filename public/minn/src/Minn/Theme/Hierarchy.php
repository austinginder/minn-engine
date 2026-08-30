<?php

declare(strict_types=1);

namespace Minn\Theme;

/**
 * The classic template hierarchy: the candidate file names each template
 * type tries, most specific first, as the reference resolves them. The
 * facade's get_{type}_template() functions feed these to get_query_template(),
 * which locates the first candidate the theme (child, then parent) ships.
 */
final class Hierarchy
{
    /** @return list<string> */
    public static function frontPage(): array
    {
        return ['front-page.php'];
    }

    /** @return list<string> */
    public static function home(): array
    {
        return ['home.php', 'index.php'];
    }

    /** @return list<string> */
    public static function privacyPolicy(): array
    {
        return ['privacy-policy.php'];
    }

    /** @return list<string> */
    public static function page(string $custom, string $slug, int $id): array
    {
        $templates = [];
        if ($custom !== '' && basename($custom) === $custom) {
            $templates[] = $custom;
        }
        if ($slug !== '') {
            $decoded = urldecode($slug);
            if ($decoded !== $slug) {
                $templates[] = "page-{$decoded}.php";
            }
            $templates[] = "page-{$slug}.php";
        }
        if ($id > 0) {
            $templates[] = "page-{$id}.php";
        }
        $templates[] = 'page.php';
        return $templates;
    }

    /** @return list<string> */
    public static function single(string $type, string $slug, string $custom): array
    {
        $templates = [];
        if ($custom !== '' && basename($custom) === $custom) {
            $templates[] = $custom;
        }
        if ($slug !== '') {
            $decoded = urldecode($slug);
            if ($decoded !== $slug) {
                $templates[] = "single-{$type}-{$decoded}.php";
            }
            $templates[] = "single-{$type}-{$slug}.php";
        }
        $templates[] = "single-{$type}.php";
        $templates[] = 'single.php';
        return $templates;
    }

    /** @return list<string> */
    public static function attachment(string $mimeType): array
    {
        $templates = [];
        [$type, $subtype] = array_pad(explode('/', $mimeType, 2), 2, '');
        foreach (array_filter([$type, $subtype, $type !== '' && $subtype !== '' ? "{$type}-{$subtype}" : '']) as $name) {
            $templates[] = "{$name}.php";
        }
        $templates[] = 'attachment.php';
        return $templates;
    }

    /** @return list<string> */
    public static function term(string $taxonomy, string $slug, int $id): array
    {
        $decoded = urldecode($slug);
        $base = in_array($taxonomy, ['category', 'post_tag'], true) ? ($taxonomy === 'post_tag' ? 'tag' : 'category') : null;
        if ($base !== null) {
            $templates = $decoded !== $slug ? ["{$base}-{$decoded}.php"] : [];
            return [...$templates, "{$base}-{$slug}.php", "{$base}-{$id}.php", "{$base}.php"];
        }
        $templates = $decoded !== $slug ? ["taxonomy-{$taxonomy}-{$decoded}.php"] : [];
        return [...$templates, "taxonomy-{$taxonomy}-{$slug}.php", "taxonomy-{$taxonomy}.php", 'taxonomy.php'];
    }

    /** @return list<string> */
    public static function author(string $nicename, int $id): array
    {
        $templates = [];
        if ($nicename !== '') {
            $templates[] = "author-{$nicename}.php";
        }
        if ($id > 0) {
            $templates[] = "author-{$id}.php";
        }
        $templates[] = 'author.php';
        return $templates;
    }

    /** @param list<string> $postTypes @return list<string> */
    public static function archive(array $postTypes): array
    {
        $templates = [];
        if (count($postTypes) === 1) {
            $templates[] = 'archive-' . $postTypes[0] . '.php';
        }
        $templates[] = 'archive.php';
        return $templates;
    }
}
