<?php
/**
 * Plugin Name: Minn test login
 * Description: Fixture for the login-hooks suite, loaded by the engine and the reference alike. A request that carries X-Minn-Login naming a run the suite opened (wp-content/minn-login/<run>.open exists) gets a plugin on the sign-in page as captcha, branding and redirect plugins are: a style, a head tag, a message, a hidden field inside the form that sign-in then requires (a captcha's check, through authenticate), a footer mark, a body class, a title, a header link and text, its own landing after signing in and out, and a field the lost-password form then requires; the login actions it hears are appended to <run>.log. Without such a run the header does nothing.
 * License: MIT
 */

$minnLoginRun = (string) ($_SERVER['HTTP_X_MINN_LOGIN'] ?? '');
$minnLoginDir = (defined('WP_CONTENT_DIR') ? WP_CONTENT_DIR : dirname(__DIR__)) . '/minn-login';
if (preg_match('/^[a-z0-9-]{1,64}$/', $minnLoginRun) !== 1 || !is_file("{$minnLoginDir}/{$minnLoginRun}.open")) {
    return;
}
$minnLoginHeard = static function (string $what) use ($minnLoginDir, $minnLoginRun): void {
    file_put_contents("{$minnLoginDir}/{$minnLoginRun}.log", $what . "\n", FILE_APPEND);
};
foreach (['login_init', 'login_form_login', 'login_form_logout', 'login_form_lostpassword', 'wp_login', 'wp_logout', 'wp_login_failed', 'lostpassword_post', 'retrieve_password'] as $minnLoginAction) {
    add_action($minnLoginAction, static fn () => $minnLoginHeard($minnLoginAction));
}
add_action('login_enqueue_scripts', static function () use ($minnLoginHeard): void {
    $minnLoginHeard('login_enqueue_scripts');
    wp_enqueue_style('zz-login', 'https://zz-login.example/zz-login.css', [], '1');
});
add_action('login_head', static function (): void {
    echo '<meta name="zz-login-head" content="1">';
});
add_filter('login_message', static fn ($message) => $message . '<p class="zz-message">Zz message</p>');
add_action('login_form', static function (): void {
    echo '<input type="hidden" name="zz_human" value="yes">';
});
add_action('login_footer', static function (): void {
    echo '<span class="zz-footer"></span>';
});
add_filter('login_body_class', static fn ($classes) => [...(array) $classes, 'zz-body']);
add_filter('login_title', static fn () => 'Zz Title');
add_filter('login_headerurl', static fn () => 'https://zz-login.example/');
add_filter('login_headertext', static fn () => 'Zz Header');
// A captcha's check: a sign-in without the form's own field is refused.
add_filter('authenticate', static function ($user) {
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && ($_POST['zz_human'] ?? '') !== 'yes') {
        return new WP_Error('zz_not_human', 'Zz: prove you are human.');
    }
    return $user;
}, 30);
add_filter('login_redirect', static fn ($to, $requested, $user) => $user instanceof WP_User ? home_url('/zz-landing/') : $to, 10, 3);
add_filter('logout_redirect', static fn () => home_url('/zz-left/'));
// The lost-password form's own captcha: its field, and a refusal without it.
add_action('lostpassword_form', static function (): void {
    echo '<input type="hidden" name="zz_human_lost" value="yes">';
});
add_action('lostpassword_post', static function ($errors): void {
    if (($_POST['zz_human_lost'] ?? '') !== 'yes') {
        $errors->add('zz_lost', 'Zz: prove it to reset.');
    }
});
