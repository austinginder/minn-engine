<?php

declare(strict_types=1);

namespace Minn\Media;

use Minn\I18n\Gettext;
use Minn\Json;
use Minn\Runtime\Assets;
use Minn\Runtime\Runtime;
use Minn\Runtime\SiteUrls;
use Minn\Runtime\Translations;

/**
 * The media player's data as the reference gives MediaElement.js (probe
 * media-player): its strings in the language in force, and the site's
 * settings for it.
 */
final class Player
{
    private const FOLDER = '/wp-includes/js/mediaelement/';

    /**
     * The player's scripts as the reference registers them: MediaElement.js
     * (MIT, served from minn/assets/vendor), the engine's migration script
     * and start-up in the reference's own files' place, all in the footer.
     * Their strings and settings come with data().
     */
    public static function register(?Assets $scripts): void
    {
        if ($scripts === null) {
            return;
        }
        foreach (['mediaelement-core' => ['mediaelement-and-player.min.js', [], '4.2.17'], 'mediaelement-migrate' => ['mediaelement-migrate.min.js', [], false], 'mediaelement' => [false, ['jquery', 'mediaelement-core', 'mediaelement-migrate'], '4.2.17'], 'mediaelement-vimeo' => ['renderers/vimeo.min.js', ['mediaelement'], '4.2.17'], 'wp-mediaelement' => ['wp-mediaelement.min.js', ['mediaelement'], false]] as $handle => [$file, $deps, $version]) {
            $scripts->register($handle, $file === false ? false : self::FOLDER . $file, $deps, $version, 1);
        }
    }

    /** The player's styles: MediaElement.js's own and the engine's skin. */
    public static function registerStyles(?Assets $styles): void
    {
        $styles?->register('mediaelement', self::FOLDER . 'mediaelementplayer-legacy.min.css', [], '4.2.17', null);
        $styles?->register('wp-mediaelement', self::FOLDER . 'wp-mediaelement.min.css', ['mediaelement'], false, null);
    }

    /** The strings (mejsL10n, before the library) and settings (_wpmejsSettings) given to the player's handles. */
    public static function data(?Assets $scripts): void
    {
        $scripts?->addInline('mediaelement-core', self::strings(), 'before');
        $scripts?->localize('mediaelement', '_wpmejsSettings', self::settings());
    }

    /** The script that defines mejsL10n: the language determine_locale names, and each string translated. */
    public static function strings(): string
    {
        $language = strtolower((string) strtok(Translations::locale(), '_-'));
        $strings = [];
        foreach ((array) Json::file(MINN_ENGINE_DIR . '/data/mediaelement-strings.json') as $key => $text) {
            $strings[(string) $key] = Gettext::text((string) $text);
        }
        return sprintf('var mejsL10n = %s;', Json::encode(['language' => $language, 'strings' => $strings], JSON_UNESCAPED_SLASHES));
    }

    /**
     * _wpmejsSettings: where the player's files are, its class prefix and
     * stretching, and the shortcode libraries, through mejs_settings.
     *
     * @return array<string, mixed>
     */
    public static function settings(): array
    {
        $hooks = Runtime::hooks();
        return (array) $hooks->filter('mejs_settings', [[
            'pluginPath' => SiteUrls::includes('js/mediaelement/'),
            'classPrefix' => 'mejs-',
            'stretching' => 'responsive',
            'audioShortcodeLibrary' => $hooks->filter('wp_audio_shortcode_library', ['mediaelement']),
            'videoShortcodeLibrary' => $hooks->filter('wp_video_shortcode_library', ['mediaelement']),
        ]]);
    }
}
