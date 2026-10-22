<?php
/**
 * media_handle_sideload titles and describes an image by what it carries:
 * IPTC title and caption (made here with iptcembed), a caption alone, a
 * headline, blank values, none; against a description given and the post
 * data. Each attachment is deleted with its files. Same protocol as
 * api-probe.php.
 */
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/media.php';
require_once ABSPATH . 'wp-admin/includes/image.php';
$iptc = static function (array $tags): string {
    $out = '';
    foreach ($tags as $tag => $value) {
        [$rec, $data] = explode('#', $tag);
        $len = strlen($value);
        $out .= chr(0x1C) . chr((int) $rec) . chr((int) $data) . chr($len >> 8) . chr($len & 0xFF) . $value;
    }
    return $out;
};
$log = [];
$cases = [
    'title and caption' => [['2#005' => 'ZZ IPTC Title', '2#120' => 'ZZ IPTC caption here'], null, []],
    'caption only' => [['2#120' => 'ZZ only a caption'], null, []],
    'headline' => [['2#105' => 'ZZ Headline'], null, []],
    'blank title and caption' => [['2#005' => '   ', '2#120' => '  '], null, []],
    'none' => [[], null, []],
    'a description given' => [['2#005' => 'ZZ IPTC Title', '2#120' => 'ZZ cap'], 'ZZ Desc', []],
    'post data over both' => [['2#005' => 'ZZ IPTC Title', '2#120' => 'ZZ cap'], null, ['post_title' => 'ZZ PD', 'post_content' => 'ZZ PD content']],
];
foreach ($cases as $case => [$tags, $desc, $postData]) {
    $im = imagecreatetruecolor(20, 10);
    $tmp = wp_tempnam('zzside');
    imagejpeg($im, $tmp);
    if ($tags) {
        file_put_contents($tmp, iptcembed($iptc($tags), $tmp));
    }
    $id = media_handle_sideload(['name' => 'zz-sideload-meta.jpg', 'tmp_name' => $tmp], 0, $desc, $postData);
    if (is_wp_error($id)) {
        $log[] = [$case, $id->get_error_code()];
        @unlink($tmp);
        continue;
    }
    $p = get_post($id);
    $log[] = [$case, [$p->post_title, $p->post_excerpt, $p->post_content]];
    if (is_int($id) && $id > 1) {
        wp_delete_attachment($id, true);
    }
}
echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE), "\n";
