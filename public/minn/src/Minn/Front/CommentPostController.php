<?php

declare(strict_types=1);

namespace Minn\Front;

use Minn\Http\Method;
use Minn\Http\Request;
use Minn\Http\Response;
use Minn\Http\Access;
use Minn\Http\Policy;
use Minn\Http\Route;
use Minn\Runtime\Runtime;

/**
 * wp-comments-post.php: the comment form's target. The reference's
 * answers, captured: 302 to the comment's anchor (with unapproved and
 * moderation-hash when held), plain pages with 200 for a missing field,
 * 403 when comments are closed, 409 for a duplicate, 429 for posting too
 * quickly, and 405 for anything but POST.
 */
final readonly class CommentPostController
{
    /** The comment form's target. */
    #[Route(Method::Any, '/wp-comments-post.php', policy: new Policy(Access::Public))]
    public function post(Request $request): Response
    {
        if ($request->method !== Method::Post) {
            return new Response(405, ['Allow' => 'POST', 'Content-Type' => 'text/plain;charset=UTF-8'], 'Error: Method not allowed.');
        }
        return $this->submit($request);
    }

    /**
     * The reference's own submission and redirect (contracts/runtime.md "The
     * comment form"), so a spam plugin's checks, the notifications and the
     * commenter cookies are WordPress's: a refusal with a status is the
     * refusal page, one without is a blank page; else set_comment_cookies,
     * the comment's link (or redirect_to), the held comment's id and hash
     * when the commenter is not remembered, comment_post_redirect, and
     * wp_safe_redirect's checks.
     */
    private function submit(Request $request): Response
    {
        $comment = \wp_handle_comment_submission($request->form);
        if ($comment instanceof \WP_Error) {
            $status = (int) $comment->get_error_data();
            return $status > 0 ? self::refusal($comment->get_error_message(), $status) : new Response(200, ['Content-Type' => 'text/html; charset=UTF-8'], '');
        }
        $consent = isset($request->form['wp-comment-cookies-consent']);
        \do_action('set_comment_cookies', $comment, \wp_get_current_user(), $consent);
        $redirect = (string) ($request->form['redirect_to'] ?? '');
        $location = $redirect === '' ? (string) \get_comment_link($comment) : $redirect . '#comment-' . $comment->comment_ID;
        if (!$consent && \wp_get_comment_status($comment) === 'unapproved' && (string) $comment->comment_author_email !== '') {
            $location = \add_query_arg(['unapproved' => $comment->comment_ID, 'moderation-hash' => \wp_hash($comment->comment_date_gmt)], $location);
        }
        \wp_safe_redirect(\apply_filters('comment_post_redirect', $location, $comment));
        $sent = (array) Runtime::current()->get('redirect', []);
        $response = Response::redirect((string) ($sent['location'] ?? $location), (int) ($sent['status'] ?? 302));
        return is_string($sent['by'] ?? null) ? $response->withHeader('X-Redirect-By', (string) $sent['by']) : $response;
    }

    /** The reference's plain refusal page: the message and a way back. */
    private static function refusal(string $message, int $status): Response
    {
        return Response::html(
            '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Comment Submission Failure</title>'
            . '<style>body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;background:#0b0b0d;color:#ececed;font:16px/1.6 "Helvetica Neue",sans-serif}main{max-width:34rem;padding:2rem}p{margin:0 0 .6rem}a{color:#ececed}</style></head>'
            . '<body><main><p>' . $message . '</p><p><a href="javascript:history.back()">&laquo; Back</a></p></main></body></html>',
            $status,
        );
    }
}
