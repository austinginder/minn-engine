<?php

declare(strict_types=1);

namespace Minn\Login;

use Minn\Support\Html;

/** The sign-in page markup. */
final class LoginForm
{
    public static function render(string $siteName, string $action, string $redirectTo, string $error, string $message = ''): string
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
            . '<label class="row"><input type="checkbox" name="rememberme" value="forever"> Remember Me</label>'
            . $redirectField
            . '<button type="submit" name="wp-submit">Log In</button>'
            . '<p class="hint"><a href="' . Html::attr($action . '?action=lostpassword') . '">Lost your password?</a></p>'
            . '</form></body></html>';
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
        .row { display:flex; align-items:center; gap:7px; margin-top:14px; font-size:13px; color:#9d9da7; }
        button { margin-top:18px; width:100%; padding:10px; background:#6459f0; color:#fff; border:0;
               border-radius:8px; font-size:14px; font-weight:600; cursor:pointer; }
        .err { background:rgba(228,107,107,.12); border:1px solid rgba(228,107,107,.4); color:#e46b6b;
               padding:9px 11px; border-radius:8px; font-size:13px; margin-bottom:14px; }
        CSS;
}
