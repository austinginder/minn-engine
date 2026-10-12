<?php

declare(strict_types=1);

namespace Minn\I18n;

/**
 * Which file a request for a .mo path reads, and reading it. Asked for
 * "x.mo", the reference reads "x.l10n.php" when the format is php and that
 * file is there, and the .mo only otherwise; asked for the mo format, only
 * the .mo.
 */
final class TranslationFiles
{
    /**
     * The files to try for a requested .mo path, in order.
     *
     * @return list<string>
     */
    public static function candidates(string $mofile, string $format): array
    {
        if ($format === 'mo') {
            return [$mofile];
        }
        $php = str_ends_with($mofile, '.mo') ? substr($mofile, 0, -3) . '.l10n.php' : $mofile . '.l10n.php';
        return [$php, $mofile];
    }

    /**
     * The languages a folder holds core translations for (each .mo but the
     * time zone names, the network admin's and the admin's), as
     * get_available_languages lists them, through its filter ($asked: the
     * folder as the caller named it, null for the site's own).
     *
     * @return list<string>
     */
    public static function languages(string $dir, ?string $asked = null): array
    {
        $out = [];
        foreach (glob($dir . '/*.mo') ?: [] as $file) {
            $name = basename($file, '.mo');
            if (!str_starts_with($name, 'continents-cities') && !str_starts_with($name, 'ms-') && !str_starts_with($name, 'admin-')) {
                $out[] = $name;
            }
        }
        return array_values((array) \Minn\Runtime\Runtime::hooks()->filter('get_available_languages', [$out, $asked]));
    }

    /** A translation file's catalog by its extension, or null when it cannot be read as one. */
    public static function read(string $file): ?Catalog
    {
        if (!is_file($file) || !is_readable($file)) {
            return null;
        }
        if (str_ends_with($file, '.php')) {
            return PhpFile::read($file);
        }
        return MoFile::read((string) file_get_contents($file));
    }
}
