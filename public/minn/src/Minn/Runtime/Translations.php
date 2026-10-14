<?php

declare(strict_types=1);

namespace Minn\Runtime;

use Minn\I18n\SiteLanguage;
use Minn\I18n\TranslationFiles;
use Minn\Objects\User;

/**
 * The translations a request loads, as WordPress's l10n functions load
 * them: the locale in force, a text domain loaded from its file (or its
 * folder named for later) and unloaded, the default domain, a plugin's,
 * a user's locale, and the translations installed on disk; each step with
 * the filters that may answer first.
 */
final class Translations
{
    /** The locale in force (determine_locale): a switched one, else pre_determine_locale, else the site's through determine_locale. */
    public static function locale(): string
    {
        if (Runtime::locales()->switched()) {
            return (string) Runtime::locales()->current();
        }
        $locale = Runtime::hooks()->filter('pre_determine_locale', [null]);
        if (is_string($locale) && $locale !== '') {
            return $locale;
        }
        return (string) Runtime::hooks()->filter('determine_locale', [SiteLanguage::locale()]);
    }

    /**
     * A text domain loaded from a translation file (load_textdomain), the
     * PHP file beside the .mo first unless translation_file_format says mo;
     * whether one loaded. Its locale becomes the one translations are looked
     * up in, and its folder where its script translations are looked for.
     */
    public static function load(mixed $domain, mixed $mofile, mixed $locale = null): bool
    {
        $hooks = Runtime::hooks();
        $loaded = $hooks->filter('pre_load_textdomain', [null, $domain, $mofile, $locale]);
        if ($loaded !== null) {
            return (bool) $loaded;
        }
        if ($hooks->filter('override_load_textdomain', [false, $domain, $mofile, $locale])) {
            return true;
        }
        $hooks->action('load_textdomain', [$domain, $mofile]);
        $mofile = (string) $hooks->filter('load_textdomain_mofile', [$mofile, $domain]);
        $locale ??= self::locale();
        $controller = Handoff::translationController();
        $controller->set_locale((string) $locale);
        $format = $hooks->filter('translation_file_format', ['php', $domain]);
        foreach (TranslationFiles::candidates($mofile, $format === 'mo' ? 'mo' : 'php') as $candidate) {
            $file = (string) $hooks->filter('load_translation_file', [$candidate, $domain, $locale]);
            if ($controller->load_file($file, (string) $domain, (string) $locale)) {
                $GLOBALS['wp_textdomain_registry']->set((string) $domain, (string) $locale, dirname($file));
                Runtime::textDomains()->accept((string) $domain);
                return true;
            }
        }
        return false;
    }

    /** A text domain unloaded (unload_textdomain): whether it had files; one that did stays closed unless it may load again. */
    public static function unload(mixed $domain, mixed $reloadable = false): bool
    {
        $hooks = Runtime::hooks();
        if ($hooks->filter('override_unload_textdomain', [false, $domain, $reloadable])) {
            return true;
        }
        $hooks->action('unload_textdomain', [$domain, $reloadable]);
        $had = Runtime::textDomains()->unload((string) $domain);
        if ($had && !$reloadable) {
            Runtime::textDomains()->close((string) $domain);
        }
        return $had;
    }

    /** A domain loaded from a folder's file for the locale when there is one, or the folder named for later; a domain closed for good loads nothing. */
    public static function fromFolder(string $domain, string $folder, string $file, string $locale): bool
    {
        $folder = rtrim($folder, '/');
        $mofile = "{$folder}/{$file}.mo";
        if (!Runtime::textDomains()->closed($domain) && (is_readable($mofile) || is_readable("{$folder}/{$file}.l10n.php"))) {
            return self::load($domain, $mofile, $locale);
        }
        Runtime::textDomains()->rememberFolder($domain, $folder);
        return true;
    }

    /** The default domain loaded again for a locale, the one in force unless named (load_default_textdomain). */
    public static function loadDefault(mixed $locale = null): bool
    {
        $locale = (string) ($locale ?? self::locale());
        self::unload('default', true);
        return self::load('default', WP_LANG_DIR . "/{$locale}.mo", $locale);
    }

    /** A plugin's domain loaded from its folder (load_plugin_textdomain), the plugins folder unless a path under it is given. */
    public static function loadPlugin(mixed $domain, mixed $deprecated = false, mixed $relativePath = false): bool
    {
        $folder = WP_PLUGIN_DIR . ($relativePath !== false ? '/' . trim((string) $relativePath, '/') : '');
        $locale = self::locale();
        return self::fromFolder((string) $domain, $folder, "{$domain}-{$locale}", $locale);
    }

    /** A user's locale (get_user_locale), the signed-in user's for 0; the site's when the user has none or does not exist. */
    public static function userLocale(mixed $user = 0): string
    {
        $object = match (true) {
            $user instanceof User => $user,
            (int) $user === 0 && !is_object($user) => Handoff::currentUser(),
            is_numeric($user) => Handoff::user((int) $user),
            default => false,
        };
        $locale = $object instanceof User ? (string) MetaData::get('user', $object->ID, 'locale', true) : '';
        return $locale !== '' ? $locale : SiteLanguage::locale();
    }

    /**
     * The translations installed (wp_get_installed_translations) for core,
     * plugins or themes: by text domain and locale, each with its file's
     * headers; a .po without its .mo is not one.
     *
     * @return array<string, array<string, array<string, string>>>
     */
    public static function installed(mixed $type): array
    {
        $dir = WP_LANG_DIR . ($type === 'core' ? '' : '/' . $type);
        $out = [];
        foreach (glob($dir . '/*.po') ?: [] as $file) {
            $base = basename($file, '.po');
            if (!is_file(substr($file, 0, -3) . '.mo') || !preg_match('/^(?:(.+)-)?([a-z]{2,3}(?:_[A-Z]{2})?(?:_[a-z]+)?)$/', $base, $m)) {
                continue;
            }
            $domain = $m[1] !== '' ? $m[1] : 'default';
            $out[$domain][$m[2]] = FileHeaders::read($file, ['POT-Creation-Date' => '"POT-Creation-Date', 'PO-Revision-Date' => '"PO-Revision-Date', 'Project-Id-Version' => '"Project-Id-Version', 'X-Generator' => '"X-Generator']);
        }
        return $out;
    }
}
