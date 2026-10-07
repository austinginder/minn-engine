<?php

declare(strict_types=1);

namespace Minn\Theme;

use Minn\Front\Kind;
use Minn\Front\Resolution;

/**
 * The body-class list a classic theme's body_class() starts from, in the
 * reference's order: the query tokens (with the singular and template
 * tokens spliced in front of the type token), logged-in, the embed and
 * theme tokens, with the numbered paging tokens re-seated after the embed
 * token. The body_class filter runs over this list in the facade.
 */
final class BodyClasses
{
    /**
     * The body classes a classic theme's page carries.
     *
     * @param list<string> $coreClasses
     * @return list<string>
     */
    public static function classic(
        Resolution $resolution,
        array $coreClasses,
        ?string $customTemplate,
        string $themeSlug,
        ?string $parentSlug,
        BodyFacts $facts,
    ): array {
        $paging = array_values(array_filter($coreClasses, static fn (string $c) => preg_match('/^(?:[a-z-]+-)?paged-\d+$/', $c) === 1));
        $classes = array_values(array_diff($coreClasses, $paging));
        // The numbered paging tokens seat after the embed token.
        $classes = self::withSingularTokens($resolution, $classes, $customTemplate);
        if ($facts->loggedIn) {
            $classes[] = 'logged-in';
        }
        if ($facts->customLogo) {
            $classes[] = 'wp-custom-logo';
        }
        if ($facts->embedResponsive) {
            $classes[] = 'wp-embed-responsive';
        }
        array_push($classes, ...$paging);
        $classes[] = 'wp-theme-' . ($parentSlug ?? $themeSlug);
        if ($parentSlug !== null) {
            $classes[] = 'wp-child-theme-' . $themeSlug;
        }
        if ($facts->bar) {
            $classes[] = 'minn-front-bar';
        }
        return $classes;
    }

    /**
     * @param list<string> $classes
     * @return list<string>
     */
    private static function withSingularTokens(Resolution $resolution, array $classes, ?string $customTemplate): array
    {
        if ($resolution->kind === Kind::Single) {
            $at = (int) array_search('single', $classes, true);
            array_splice($classes, $at, 0, ['wp-singular', (string) ($resolution->record['post_type'] ?? 'post') . '-template-default']);
        } elseif ($resolution->kind === Kind::Page) {
            $tokens = $customTemplate === null || $customTemplate === ''
                ? ['wp-singular', 'page-template-default']
                : ['wp-singular', 'page-template', 'page-template-' . preg_replace('/[^a-z0-9_-]+/', '-', strtolower(str_replace('.', '-', $customTemplate)))];
            $at = (int) array_search('page', $classes, true);
            array_splice($classes, $at, 0, $tokens);
        }
        return $classes;
    }
}
