<?php

declare(strict_types=1);

namespace Minn\Theme;

/**
 * A theme's pattern files read by their headers, as WP_Theme::get_block_patterns
 * lists them (probe wp-theme). Each one gives title, slug and description
 * (empty when the header has none), then whatever of viewportWidth (a
 * number), inserter (false for "no"), and the lists categories,
 * keywords, blockTypes, postTypes and templateTypes it declares, split on
 * commas and whitespace. A file
 * without a title and a slug is not a pattern.
 */
final class PatternHeaders
{
    private const LISTS = ['categories' => 'Categories', 'keywords' => 'Keywords', 'blockTypes' => 'Block Types', 'postTypes' => 'Post Types', 'templateTypes' => 'Template Types'];

    /**
     * The patterns directory's files by file name, in name order.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function all(string $themeDir): array
    {
        return self::of(self::files($themeDir));
    }

    /**
     * The pattern files a theme ships, file name => path, in name order.
     *
     * @return array<string, string>
     */
    public static function files(string $themeDir): array
    {
        $files = [];
        foreach (glob($themeDir . '/patterns/*.php') ?: [] as $file) {
            $files[basename($file)] = $file;
        }
        return $files;
    }

    /**
     * The records of a list of pattern files, file name => path (a list a
     * plugin may have changed); a file that is not a pattern is left out.
     *
     * @param array<string, string> $files
     * @return array<string, array<string, mixed>>
     */
    public static function of(array $files): array
    {
        $found = [];
        foreach ($files as $name => $file) {
            $record = is_file((string) $file) ? self::read((string) file_get_contents((string) $file, false, null, 0, 8192)) : null;
            if ($record !== null) {
                $found[(string) $name] = $record;
            }
        }
        return $found;
    }

    /**
     * One file's record from the start of its text, or null when it names no title or slug.
     *
     * @return array<string, mixed>|null
     */
    public static function read(string $head): ?array
    {
        $field = static fn (string $name): ?string => preg_match('/^[ \t\/*#@]*' . preg_quote($name, '/') . ':(.*)$/mi', $head, $m) === 1 ? trim($m[1]) : null;
        [$title, $slug] = [$field('Title'), $field('Slug')];
        if ($title === null || $title === '' || $slug === null || $slug === '') {
            return null;
        }
        $record = ['title' => $title, 'slug' => $slug, 'description' => (string) $field('Description')];
        $width = $field('Viewport Width');
        if ($width !== null && $width !== '') {
            $record['viewportWidth'] = (int) $width;
        }
        $inserter = $field('Inserter');
        if ($inserter !== null && $inserter !== '') {
            $record['inserter'] = in_array(strtolower($inserter), ['yes', 'true'], true);
        }
        foreach (self::LISTS as $key => $name) {
            // A list is split on commas and on whitespace alike ("frequently asked questions" is three keywords; probe block-patterns).
            $list = preg_split('/[\s,]+/', (string) $field($name), -1, PREG_SPLIT_NO_EMPTY) ?: [];
            if ($list !== []) {
                $record[$key] = $list;
            }
        }
        return $record;
    }
}
