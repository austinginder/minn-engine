<?php
/**
 * The [video] and [audio] shortcodes as the reference prints them (probe
 * av-shortcodes): a source by src and by each format's own attribute,
 * several formats at once, a YouTube or Vimeo address, a poster, sizes
 * wider than the content width, the boolean flags, preload and class;
 * nothing to play; and a plugin on each seam: the override that takes the
 * shortcode over, the class, the library, the extensions, and the final
 * HTML (each heard with what it was handed). The instance counters and
 * the content width are pinned for the probe. Read only. Same protocol as
 * api-probe.php.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$GLOBALS['content_width'] = 600;
$mask = static fn (string $html): string => (string) preg_replace(['/(id="(?:video|audio)-)\d+-(\d+)"/', '/(\?|&#038;|&amp;|&)_=\d+/'], ['$1N-$2"', '$1_=N'], $html);
$cases = [
    'video by src' => '[video src="https://zz.example/a.mp4"]',
    'video by mp4' => '[video mp4="https://zz.example/a.mp4"]',
    'video, three formats' => '[video mp4="https://zz.example/a.mp4" webm="https://zz.example/a.webm" ogv="https://zz.example/a.ogv"]',
    'video, sized wider than the content' => '[video src="https://zz.example/a.mp4" width="1200" height="600"]',
    'video, poster and flags' => '[video src="https://zz.example/a.mp4" poster="https://zz.example/p.jpg" loop="on" autoplay="1" muted="true" preload="auto" class="zz-class"]',
    'video, YouTube' => '[video src="https://www.youtube.com/watch?v=zzzzzzzzzzz"]',
    'video, Vimeo' => '[video src="https://vimeo.com/123456"]',
    'video, an unknown kind' => '[video src="https://zz.example/a.txt"]',
    'video, nothing' => '[video]',
    'audio by src' => '[audio src="https://zz.example/a.mp3"]',
    'audio, two formats and flags' => '[audio mp3="https://zz.example/a.mp3" ogg="https://zz.example/a.ogg" loop="1" autoplay="1" preload="auto" class="zz-audio" style="width: 50%"]',
    'audio, nothing' => '[audio]',
];
$out = [];
foreach ($cases as $label => $code) {
    $out[$label] = $mask(do_shortcode($code));
}
$say('the shortcodes', $out);

$heard = [];
$hooks = [
    'wp_video_shortcode_override' => static function ($html, $attr, $content, $instance) use (&$heard) {
        $heard[] = ['wp_video_shortcode_override', $html, $attr, $content, $instance];
        return ($attr['src'] ?? '') === 'https://zz.example/over.mp4' ? '<p>zz overridden</p>' : $html;
    },
    'wp_video_shortcode_class' => static function ($class, $attr) use (&$heard) {
        $heard[] = ['wp_video_shortcode_class', $class, array_keys((array) $attr)];
        return $class . ' zz-more';
    },
    'wp_video_shortcode_library' => static function ($library) use (&$heard) {
        $heard[] = ['wp_video_shortcode_library', $library];
        return $library;
    },
    'wp_video_extensions' => static function ($extensions) use (&$heard) {
        $heard[] = ['wp_video_extensions', $extensions];
        return $extensions;
    },
    'wp_video_shortcode' => static function ($html, $attr, $video, $post_id, $library) use (&$heard) {
        $heard[] = ['wp_video_shortcode', array_keys((array) $attr), $video, $library];
        return $html . '<!-- zz -->';
    },
    'wp_audio_shortcode_override' => static function ($html, $attr, $content, $instance) use (&$heard) {
        $heard[] = ['wp_audio_shortcode_override', $html, $attr, $content, $instance];
        return $html;
    },
    'wp_audio_shortcode_class' => static function ($class, $attr) use (&$heard) {
        $heard[] = ['wp_audio_shortcode_class', $class, array_keys((array) $attr)];
        return $class;
    },
    'wp_audio_shortcode' => static function ($html, $attr, $audio, $post_id, $library) use (&$heard) {
        $heard[] = ['wp_audio_shortcode', array_keys((array) $attr), $audio, $library];
        return $html;
    },
];
$args = ['wp_video_shortcode_override' => 4, 'wp_video_shortcode_class' => 2, 'wp_video_shortcode' => 5, 'wp_audio_shortcode_override' => 4, 'wp_audio_shortcode_class' => 2, 'wp_audio_shortcode' => 5];
foreach ($hooks as $hook => $callback) {
    add_filter($hook, $callback, 10, $args[$hook] ?? 1);
}
$heard = [];
$with = [
    'a plugin on the video' => $mask(do_shortcode('[video src="https://zz.example/a.mp4" width="320" height="240"]')),
    'a plugin takes it over' => $mask(do_shortcode('[video src="https://zz.example/over.mp4"]')),
    'a plugin on the audio' => $mask(do_shortcode('[audio src="https://zz.example/a.mp3"]')),
];
$say('with a plugin on them', [$with, array_map(static fn ($h) => array_map(static fn ($v) => is_string($v) ? $mask($v) : $v, $h), $heard)]);
foreach ($hooks as $hook => $callback) {
    remove_filter($hook, $callback, 10);
}

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
