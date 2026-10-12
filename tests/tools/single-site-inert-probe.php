<?php
/**
 * Functions a single site loads whose hooks fire only on a network (probe
 * single-site-inert): each is called as a plugin would call it, and the
 * results are compared. The hook-parity battery records this probe too, so
 * contracts/api/hooks-single-site.json can show, from the reference's own
 * capture, that a single site never fires the hooks it lists.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
// WP-CLI forces site_url()'s scheme on one stack and not the other: the scheme is masked with the host.
$host = (string) parse_url(home_url(), PHP_URL_HOST);
$mask = static fn ($value) => is_string($value) ? (string) preg_replace('#^https?://' . preg_quote($host, '#') . '#', '{home}', $value) : $value;

$say('grant_super_admin', grant_super_admin(1));
$say('revoke_super_admin', revoke_super_admin(1));
$say('network_site_url', $mask(network_site_url('/x')));
$say('network_home_url', $mask(network_home_url('/x')));
$say('network_admin_url', $mask(network_admin_url('x.php')));
$say('get_main_network_id', get_main_network_id());
$say('get_main_site_id', get_main_site_id());
$say('is_user_member_of_blog', is_user_member_of_blog(1));
$say('get_blogs_of_user', count(get_blogs_of_user(1)));
$say('wp_authenticate_spam_check', wp_authenticate_spam_check(get_userdata(1)) instanceof WP_User);
$say('get_allowed_on_network', WP_Theme::get_allowed_on_network());
$say('get_allowed_on_site', WP_Theme::get_allowed_on_site());
$say('get_allowed', WP_Theme::get_allowed());

echo json_encode($log, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), "\n";
