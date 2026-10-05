<?php

declare(strict_types=1);

namespace Minn\I18n;

/**
 * The PHP translation file (.l10n.php): a file that returns an array of its
 * lower-cased headers and a "messages" map from the gettext key to the
 * translation, plural forms joined by NUL bytes. It is the site's own data,
 * read the way the reference reads it, by including it.
 */
final class PhpFile
{
    /** The catalog a .l10n.php file returns, or null when it returns no messages. */
    public static function read(string $file): ?Catalog
    {
        $data = (static fn (string $path): mixed => include $path)($file);
        if (!is_array($data) || !is_array($data['messages'] ?? null)) {
            return null;
        }
        $headers = [];
        foreach ($data as $name => $value) {
            if ($name !== 'messages' && is_scalar($value)) {
                $headers[(string) $name] = (string) $value;
            }
        }
        $entries = [];
        foreach ($data['messages'] as $key => $translation) {
            $entries[(string) $key] = ['translations' => explode("\0", (string) $translation), 'plural' => null];
        }
        return Catalog::from($headers, $entries);
    }
}
