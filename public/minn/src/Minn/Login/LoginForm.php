<?php

declare(strict_types=1);

namespace Minn\Login;

use Minn\Support\Html;

/** The sign-in page markup. */
final class LoginForm
{
    /** The sign-in page's HTML. */
    public static function render(string $siteName, string $action, string $redirectTo, string $error, string $message = '', string $lostPasswordUrl = ''): string
    {
        $site = Html::esc($siteName);
        $redirect = Html::esc($redirectTo);
        $errorHtml = ($error === '' ? '' : '<div class="err">' . Html::esc($error) . '</div>')
            . ($message === '' ? '' : '<div class="msg">' . Html::esc($message) . '</div>');
        $redirectField = $redirect === '' ? '' : '<input type="hidden" name="redirect_to" value="' . $redirect . '">';

        return '<!doctype html><html lang="en"><head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<title>Log In &lsaquo; ' . $site . '</title>'
            . '<style>' . self::CSS . '</style></head><body>'
            . '<form method="post" action="' . Html::attr($action) . '">'
            . '<h1>' . $site . '</h1>' . $errorHtml
            . '<label for="log">Username or Email</label>'
            . '<input type="text" name="log" id="log" autocapitalize="none" autocomplete="username" autofocus>'
            . '<label for="pwd">Password</label>'
            . '<input type="password" name="pwd" id="pwd" autocomplete="current-password">'
            . '<label class="switch"><input type="checkbox" name="rememberme" value="forever" role="switch"><span class="knob" aria-hidden="true"></span><span>Remember me</span></label>'
            . $redirectField
            . '<button type="submit" name="wp-submit">Log In</button>'
            . '<p class="hint"><a href="' . Html::attr($lostPasswordUrl !== '' ? $lostPasswordUrl : $action . '?action=lostpassword') . '">Lost your password?</a></p>'
            . '</form></body></html>';
    }

    /**
     * The sign-in form a theme embeds in a page, as distinct from the engine's
     * own sign-in page above. Every label, id and class is a seam themes and
     * plugins style against, so the shape is fixed.
     *
     * @param array<string, mixed> $args the parsed wp_login_form arguments
     */
    public static function embedded(array $args, string $top, string $middle, string $bottom): string
    {
        // The reference indents the fields inside each paragraph, and themes
        // have been matching that markup for years, so the whitespace is part
        // of the contract rather than formatting.
        $indent = "\n\t\t\t\t";
        $close = "\n\t\t\t";
        $remember = $args['remember']
            ? '<p class="login-remember"><label><input name="rememberme" type="checkbox" id="' . Html::attr($args['id_remember'])
                . '" value="forever"' . ($args['value_remember'] ? ' checked="checked"' : '') . ' /> ' . Html::esc($args['label_remember']) . '</label></p>'
            : '';
        $username = '<p class="login-username">' . $indent
            . '<label for="' . Html::attr($args['id_username']) . '">' . Html::esc($args['label_username']) . '</label>' . $indent
            . '<input type="text" name="log" id="' . Html::attr($args['id_username']) . '" autocomplete="username" class="input" value="'
            . Html::attr($args['value_username']) . '" size="20" />' . $close . '</p>';
        $password = '<p class="login-password">' . $indent
            . '<label for="' . Html::attr($args['id_password']) . '">' . Html::esc($args['label_password']) . '</label>' . $indent
            . '<input type="password" name="pwd" id="' . Html::attr($args['id_password'])
            . '" autocomplete="current-password" spellcheck="false" class="input" value="" size="20" />' . $close . '</p>';
        $submit = '<p class="login-submit">' . $indent
            . '<input type="submit" name="wp-submit" id="' . Html::attr($args['id_submit']) . '" class="button button-primary" value="'
            . Html::attr($args['label_log_in']) . '" />' . $indent
            . '<input type="hidden" name="redirect_to" value="' . Html::attr($args['redirect']) . '" />' . $close . '</p>';
        return '<form name="' . Html::attr($args['form_id']) . '" id="' . Html::attr($args['form_id']) . '" action="'
            . Html::attr($args['action']) . '" method="post">'
            . $top . $username . $password . $middle . $remember . $submit . $bottom . '</form>';
    }

    /** The "forgot password" form: one field, posts to itself. */
    public static function lostPassword(string $siteName, string $action, string $error, string $message): string
    {
        return self::page($siteName, 'Lost Password', $error, $message,
            '<form method="post" action="' . Html::attr($action) . '">'
            . '<h1>' . Html::esc($siteName) . '</h1>'
            . '<p class="hint">Enter your username or email address and a link to choose a new password will be sent to you.</p>'
            . '<label for="user_login">Username or Email Address</label>'
            . '<input type="text" name="user_login" id="user_login" autocapitalize="none" autocomplete="username" autofocus required>'
            . '<button type="submit" name="wp-submit">Get New Password</button>'
            . '</form>');
    }

    /** The new-password form; the key rides in a hidden field as on the reference. */
    public static function resetPassword(string $siteName, string $action, string $key, string $login, string $error): string
    {
        return self::page($siteName, 'Reset Password', $error, '',
            '<form method="post" action="' . Html::attr($action) . '" autocomplete="off">'
            . '<h1>' . Html::esc($siteName) . '</h1>'
            . '<label for="pass1">New password</label>'
            . '<input type="password" name="pass1" id="pass1" autocomplete="new-password" spellcheck="false" required>'
            . '<label for="pass2">Confirm new password</label>'
            . '<input type="password" name="pass2" id="pass2" autocomplete="new-password" spellcheck="false" required>'
            . '<input type="hidden" name="rp_key" value="' . Html::attr($key) . '">'
            . '<input type="hidden" name="user_login" value="' . Html::attr($login) . '">'
            . '<button type="submit" name="wp-submit">Save Password</button>'
            . '</form>');
    }

    /** A message with a link back to sign-in. */
    public static function notice(string $siteName, string $title, string $message, string $loginUrl): string
    {
        return self::page($siteName, $title, '', '',
            '<form><h1>' . Html::esc($siteName) . '</h1><p class="hint">' . Html::esc($message) . '</p>'
            . '<p class="hint"><a href="' . Html::attr($loginUrl) . '">Log in</a></p></form>');
    }

    private static function page(string $siteName, string $title, string $error, string $message, string $form): string
    {
        $errorHtml = $error === '' ? '' : '<div class="err">' . Html::esc($error) . '</div>';
        $messageHtml = $message === '' ? '' : '<div class="msg">' . Html::esc($message) . '</div>';
        return '<!doctype html><html lang="en"><head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<title>' . Html::esc($title) . ' &lsaquo; ' . Html::esc($siteName) . '</title>'
            . '<style>' . self::CSS . '</style></head><body>'
            . preg_replace('/<h1>(.*?)<\/h1>/', '<h1>$1</h1>' . $errorHtml . $messageHtml, $form, 1)
            . '</body></html>';
    }

    private const CSS = <<<'CSS'
        body { margin:0; min-height:100vh; display:flex; align-items:center; justify-content:center;
               background:#0b0b0d; color:#ececed; font:15px/1.5 "Hanken Grotesk","Helvetica Neue",sans-serif; }
        form { width:320px; background:#151518; border:1px solid #242429; border-radius:14px; padding:28px; }
        h1 { font-size:19px; font-weight:700; margin:0 0 18px; }
        .hint { font-size:13px; color:#9d9da7; margin:0 0 12px; } .hint a { color:#ececed; }
        .msg { background:#14291c; border:1px solid #1f4a2c; border-radius:8px; padding:9px 12px; font-size:13px; margin-bottom:12px; }
        label { display:block; font-size:12px; color:#9d9da7; margin:14px 0 5px; }
        input[type=text],input[type=password] { width:100%; box-sizing:border-box; padding:9px 11px;
               background:#0b0b0d; border:1px solid #31313a; border-radius:8px; color:#ececed; font-size:14px; }
        .switch { display:flex; align-items:center; gap:10px; margin-top:16px; font-size:13px; color:#9d9da7; cursor:pointer; }
        .switch input { position:absolute; opacity:0; width:0; height:0; }
        .switch .knob { position:relative; flex:0 0 34px; width:34px; height:20px; border-radius:999px; background:#31313a;
               border:1px solid #3a3a44; transition:background .15s, border-color .15s; }
        .switch .knob::after { content:""; position:absolute; top:2px; left:2px; width:14px; height:14px; border-radius:50%;
               background:#ececed; transition:transform .15s; }
        .switch input:checked + .knob { background:#6459f0; border-color:#6459f0; }
        .switch input:checked + .knob::after { transform:translateX(14px); background:#fff; }
        .switch input:focus-visible + .knob { outline:2px solid #8a80f8; outline-offset:2px; }
        button { margin-top:18px; width:100%; padding:10px; background:#6459f0; color:#fff; border:0;
               border-radius:8px; font-size:14px; font-weight:600; cursor:pointer; }
        .err { background:rgba(228,107,107,.12); border:1px solid rgba(228,107,107,.4); color:#e46b6b;
               padding:9px 11px; border-radius:8px; font-size:13px; margin-bottom:14px; }
        CSS;
}
