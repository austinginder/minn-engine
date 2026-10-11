<?php
/**
 * The playlist block and its tracks (probe playlist-block): each block's
 * registered callback swapped, for its render only, for one that calls the
 * core function by name. Two audio attachments the probe makes; playlists of
 * them with every option, artists or images hidden, a caption, tracks in a
 * list, none, or one without an attachment; a track alone; and the
 * interactivity state the playlists leave. Playlist ids are masked in the
 * order they appear (they share wp_unique_id's counter). The attachments
 * are removed again.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
if (!did_action('init')) {
    do_action('init');
}
$made = [];
register_shutdown_function(static function () use (&$made): void {
    foreach ($made as $id) {
        if (is_int($id) && $id > 1) {
            wp_delete_attachment($id, true);
        }
    }
});
wp_set_current_user(1);
$audio = static function (string $title, array $meta) use (&$made) {
    $id = wp_insert_attachment(['post_title' => $title, 'post_mime_type' => 'audio/mpeg', 'post_status' => 'inherit', 'guid' => 'https://example.com/' . sanitize_title($title) . '-guid.mp3'], false, 0);
    if (!is_int($id) || $id <= 1) {
        fwrite(STDERR, "no fixture\n");
        exit(1);
    }
    update_post_meta($id, '_wp_attachment_metadata', $meta);
    $made[] = $id;
    return $id;
};
$a = $audio('ZZ Attached A', ['length_formatted' => '9:99', 'artist' => 'ZZ Meta Artist', 'album' => 'ZZ Meta Album']);
$b = $audio('ZZ Attached B', []);
wp_set_current_user(0);

$playlists = [];
$mask = static function (string $text) use (&$playlists, $a, $b): string {
    $text = (string) preg_replace_callback('/playlist-\d+/', static function (array $m) use (&$playlists): string {
        $playlists[$m[0]] ??= '{playlist' . (count($playlists) + 1) . '}';
        return $playlists[$m[0]];
    }, $text);
    return (string) preg_replace(['/(?<![0-9])' . $a . '(?![0-9])/', '/(?<![0-9])' . $b . '(?![0-9])/'], ['{a}', '{b}'], $text);
};
$callbacks = [
    'core/playlist' => static fn ($attributes, $content, $block) => render_block_core_playlist($attributes, $content, $block),
    'core/playlist-track' => static fn ($attributes, $content, $block) => render_block_core_playlist_track($attributes, $content, $block),
];
$render = static function (string $markup) use ($callbacks, $mask) {
    $saved = [];
    foreach ($callbacks as $name => $callback) {
        $type = WP_Block_Type_Registry::get_instance()->get_registered($name);
        $saved[$name] = $type->render_callback;
        $type->render_callback = $callback;
    }
    $html = do_blocks($markup);
    foreach ($saved as $name => $callback) {
        WP_Block_Type_Registry::get_instance()->get_registered($name)->render_callback = $callback;
    }
    return $mask(trim($html));
};
$track = static fn (array $attrs) => '<!-- wp:playlist-track ' . json_encode($attrs, JSON_UNESCAPED_SLASHES) . ' /-->';
$playlist = static fn (string $tracks, array $attrs = []) => '<!-- wp:playlist ' . ($attrs === [] ? '' : json_encode($attrs) . ' ') . '--><figure class="wp-block-playlist">' . $tracks . '</figure><!-- /wp:playlist -->';
$full = $track(['id' => $a, 'src' => 'https://example.com/zz-a.mp3', 'title' => 'ZZ Song', 'artist' => 'ZZ Band', 'album' => 'ZZ Album', 'length' => '3:21', 'image' => 'https://example.com/zz-art.jpg', 'imageAlt' => 'ZZ "art"']);
$plain = $track(['id' => $b, 'src' => 'https://example.com/zz-b.mp3', 'title' => 'ZZ Other']);
$say('playlist', [
    'two tracks' => $render($playlist($full . $plain)),
    'every option' => $render($playlist($full . $plain, ['showTracklist' => false, 'showImages' => false, 'showArtists' => false, 'showNumbers' => false, 'showTrackLength' => false, 'showPlayButtonArtwork' => true, 'waveformStyle' => 'line', 'order' => 'desc'])),
    'artist only' => $render($playlist($track(['id' => $a, 'src' => 'https://example.com/zz-a.mp3', 'title' => 'ZZ Song', 'artist' => 'ZZ Band']))),
    'album only, no title' => $render($playlist($track(['id' => $b, 'src' => 'https://example.com/zz-b.mp3', 'album' => 'ZZ Album']))),
    'in a list, with a caption' => $render('<!-- wp:playlist --><figure class="wp-block-playlist"><ol class="wp-block-playlist__tracklist">' . $plain . '</ol><figcaption class="wp-element-caption">ZZ caption</figcaption></figure><!-- /wp:playlist -->'),
    'only its attachment' => $render($playlist($track(['id' => $a]))),
    'no tracks' => $render($playlist('')),
    'a track without an attachment' => $render($playlist($track(['src' => 'https://example.com/zz-loose.mp3', 'title' => 'ZZ Loose']))),
    'a track alone' => $render($full),
    'a bare track alone' => $render($track(['id' => $b])),
    'classes of their own' => $render('<!-- wp:playlist {"className":"zz-list","align":"wide"} --><figure class="wp-block-playlist alignwide zz-list">' . $track(['id' => $b, 'title' => 'ZZ Classy', 'className' => 'zz-track']) . '</figure><!-- /wp:playlist -->'),
]);
$say('playlist state', json_decode($mask((string) json_encode(wp_interactivity_state('core/playlist'), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)), true));

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
