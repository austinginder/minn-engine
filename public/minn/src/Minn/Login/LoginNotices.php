<?php

declare(strict_types=1);

namespace Minn\Login;

use Minn\Runtime\Runtime;
use Minn\Support\Html;

/**
 * What a sign-in page tells the reader, held as the reference's pages hold
 * it: notices by code, each an error or (severity "message") a message,
 * their words as HTML. Printed, the errors are one paragraph, or a list
 * when there are several, and the messages a paragraph each; with plugins
 * loaded the sign-in page hands them through wp_login_errors first, and
 * every page prints them through login_errors and login_messages. The
 * authenticate chain's own refusals read as the engine's one sentence, so
 * a page never says which half of a sign-in was wrong.
 */
final readonly class LoginNotices
{
    /** The chain's own refusals, which the page words as one. */
    private const REFUSALS = ['empty_username', 'empty_password', 'invalid_username', 'invalid_email', 'incorrect_password', 'authentication_failed'];

    /** The one sentence for any of them. */
    private const REFUSED = '<strong>Error:</strong> The username or password you entered is incorrect.';

    /** @param list<array{0: string, 1: string, 2: string}> $notices code, HTML, severity ('' or 'message') */
    private function __construct(
        private array $notices = [],
    ) {
    }

    /** No notices. */
    public static function none(): self
    {
        return new self();
    }

    /** The engine's own plain words as one notice; an "Error:" lead takes the reference's strong label. */
    public static function plain(string $code, string $text, string $severity = ''): self
    {
        return $text === '' ? new self() : (new self())->with($code, self::words($text), $severity);
    }

    /** A sign-in refused: the chain's refusal, or (none given, or not one) the engine's one sentence. */
    public static function refused(mixed $refusal): self
    {
        $notices = self::of($refusal);
        return $notices->notices === [] ? (new self())->with('authentication_failed', self::REFUSED) : $notices;
    }

    /** The notices a WP_Error holds (a plugin may have handed back something else: then none). */
    public static function of(mixed $errors): self
    {
        if (!$errors instanceof \WP_Error) {
            return new self();
        }
        $notices = [];
        foreach ($errors->get_error_codes() as $code) {
            $severity = $errors->get_error_data($code) === 'message' ? 'message' : '';
            foreach ($errors->get_error_messages($code) as $html) {
                $notices[] = [(string) $code, (string) $html, $severity];
            }
        }
        return new self($notices);
    }

    /** These notices and one more. */
    public function with(string $code, string $html, string $severity = ''): self
    {
        return new self([...$this->notices, [$code, $html, $severity]]);
    }

    /** Whether there are none. */
    public function isEmpty(): bool
    {
        return $this->notices === [];
    }

    /** As the WP_Error plugins are handed. */
    public function wpError(): \WP_Error
    {
        $errors = new \WP_Error();
        foreach ($this->notices as [$code, $html, $severity]) {
            $errors->add($code, $html, $severity);
        }
        return $errors;
    }

    /**
     * The sign-in page's notices after wp_login_errors, which is handed
     * where a sign-in would land; without plugins, as they are.
     */
    public function forSignIn(string $redirect): self
    {
        return Runtime::booted() ? self::of(\apply_filters('wp_login_errors', $this->wpError(), $redirect)) : $this;
    }

    /**
     * The error and message areas' HTML ('' for an area with none),
     * through login_errors and login_messages with plugins loaded.
     *
     * @return array{errors: string, messages: string}
     */
    public function areas(): array
    {
        $errors = [];
        $messages = '';
        foreach ($this->notices as [$code, $html, $severity]) {
            if ($severity === 'message') {
                $messages .= "<p>{$html}</p>";
            } elseif (!in_array($code, self::REFUSALS, true)) {
                $errors[] = $html;
            } elseif (!in_array(self::REFUSED, $errors, true)) {
                $errors[] = self::REFUSED;
            }
        }
        $errorHtml = match (count($errors)) {
            0 => '',
            1 => "<p>{$errors[0]}</p>",
            default => '<ul class="login-error-list"><li>' . implode('</li><li>', $errors) . '</li></ul>',
        };
        if (!Runtime::booted()) {
            return ['errors' => $errorHtml, 'messages' => $messages];
        }
        return [
            'errors' => $errorHtml === '' ? '' : (string) \apply_filters('login_errors', $errorHtml),
            'messages' => $messages === '' ? '' : (string) \apply_filters('login_messages', $messages),
        ];
    }

    /** Plain words as notice HTML. */
    private static function words(string $text): string
    {
        return str_starts_with($text, 'Error: ') ? '<strong>Error:</strong> ' . Html::esc(substr($text, 7)) : Html::esc($text);
    }
}
