<?php
/**
 * What a signed-out commenter's fields become on the way in: the
 * pre_comment_* chains as a web request has them (kses on, as kses_init
 * leaves it for a reader without unfiltered_html), and the functions in
 * them taken one at a time. Same protocol as api-probe.php.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
foreach (['home_url', 'site_url', 'option_home', 'option_siteurl'] as $devHook) {
    remove_all_filters($devHook);
}
$home = home_url();
$rel = static fn ($v) => is_string($v) ? str_replace([$home, str_replace('https://', 'http://', $home)], '{home}', $v) : $v;
wp_set_current_user(0);
kses_init();

foreach ([
    '<a href="https://elsewhere.example/">out</a>',
    '<a href="https://elsewhere.example/" rel="nofollow">out</a>',
    '<a rel="noopener" href="https://elsewhere.example/" target="_blank">out</a>',
    '<a href="' . $home . '/inside/">in</a>',
    '<a href="/relative/">rel</a>',
    '<a href="#top">anchor</a>',
    'no link',
] as $i => $html) {
    $say("wp_rel_ugc #{$i}", $rel(wp_rel_ugc($html)));
}

foreach ([
    '<a class="mention" href="https://elsewhere.example/">@a</a>',
    '<a class="mention other" href="https://elsewhere.example/">@b</a>',
    '<span class="mention">@c</span>',
    '<a class="other" href="https://elsewhere.example/">d</a>',
    'plain',
    '<span class="other">e</span>',
    '<b class="mention extra" id="i">f</b>',
    '<span class=\'mention\'>g</span><em class="x mention">h</em>',
] as $i => $html) {
    $say("_wp_kses_sanitize_note_mention_classes #{$i}", _wp_kses_sanitize_note_mention_classes($html));
}

foreach ([
    'Hello <b>bold</b> and <script>alert(1)</script> <a href="https://elsewhere.example/" onclick="x()">link</a>',
    '<p>para</p><div>div</div><img src="x.png" alt="i"><blockquote cite="c">q</blockquote>',
    'unclosed <b>bold <i>italic',
    'entity &#128512; and &#x92; and & ampersand',
    '<a href="' . $home . '/inside/">in</a> <a href="https://elsewhere.example/" rel="nofollow">out</a>',
] as $i => $content) {
    $say("pre_comment_content #{$i}", $rel(apply_filters('pre_comment_content', $content)));
}
foreach (['Plain Name', '<b>Bold</b> Name', 'Name & "quotes"', "  spaced\tname  "] as $i => $name) {
    $say("pre_comment_author_name #{$i}", apply_filters('pre_comment_author_name', $name));
}
foreach (['https://elsewhere.example/path', ' elsewhere.example ', 'javascript:alert(1)', '<b>https://x.example</b>', ''] as $i => $url) {
    $say("pre_comment_author_url #{$i}", apply_filters('pre_comment_author_url', $url));
}
foreach (['reader@example.com', ' Reader@Example.com ', 'bad email', '<b>a@b.co</b>'] as $i => $email) {
    $say("pre_comment_author_email #{$i}", apply_filters('pre_comment_author_email', $email));
}

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
