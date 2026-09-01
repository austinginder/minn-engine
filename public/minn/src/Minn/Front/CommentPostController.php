<?php

declare(strict_types=1);

namespace Minn\Front;

use Minn\Content\PostRecord;
use Minn\Auth\AuthCookies;
use Minn\Auth\Authenticated;
use Minn\Auth\Authenticator;
use Minn\Auth\Capabilities;
use Minn\Content\CommentModeration;
use Minn\Content\Comments;
use Minn\Content\Posts;
use Minn\Content\Reader;
use Minn\Content\Site;
use Minn\Http\Method;
use Minn\Http\Request;
use Minn\Http\Response;
use Minn\Http\Route;
use Minn\Mail\Mailer;
use Minn\Support\Html;
use Minn\Support\Kses;

/**
 * wp-comments-post.php: the comment form's target. The reference's
 * answers, captured: 302 to the comment's anchor (with unapproved and
 * moderation-hash when held), plain pages with 200 for a missing field,
 * 403 when comments are closed, 409 for a duplicate, 429 for posting too
 * quickly, and 405 for anything but POST.
 */
final readonly class CommentPostController
{
    private const FLOOD_SECONDS = 15;

    public function __construct(
        private Site $site,
        private Posts $posts,
        private Comments $comments,
        private Permalinks $permalinks,
        private Authenticator $authenticator,
        private Capabilities $capabilities,
        private AuthCookies $cookies,
    ) {
    }

    #[Route(Method::Any, '/wp-comments-post.php')]
    public function post(Request $request): Response
    {
        if ($request->method !== Method::Post) {
            return new Response(405, ['Allow' => 'POST', 'Content-Type' => 'text/plain;charset=UTF-8'], 'Error: Method not allowed.');
        }
        $post = $this->posts->find((int) ($request->form['comment_post_ID'] ?? 0));
        if ($post === null) {
            return self::refusal('Invalid post.', 404);
        }
        $reader = Reader::current();
        if ($post->commentStatus !== 'open' || !in_array($post->status, ['publish', 'private'], true) || ($post->status === 'private' && !$reader->canEdit($post->id) && !$reader->readsPrivatePosts)) {
            return self::refusal('Sorry, comments are closed for this item.', 403);
        }
        $session = $this->authenticator->session($request->cookies);
        $user = $session instanceof Authenticated ? $session->user : null;
        $content = trim((string) ($request->form['comment'] ?? ''));
        if ($user !== null) {
            $author = (string) $user['display_name'];
            $email = (string) $user['user_email'];
            $url = (string) $user['user_url'];
        } else {
            if (($this->site->option('comment_registration') ?? '0') === '1') {
                return self::refusal('You must be logged in to post a comment.', 403);
            }
            $author = Kses::text((string) ($request->form['author'] ?? ''));
            $email = trim((string) ($request->form['email'] ?? ''));
            $url = Kses::url(trim((string) ($request->form['url'] ?? '')));
            if (($this->site->option('require_name_email') ?? '1') === '1' && ($author === '' || $email === '')) {
                return self::refusal('<strong>Error:</strong> Please fill the required fields.', 200);
            }
            if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                return self::refusal('<strong>Error:</strong> Please enter a valid email address.', 200);
            }
        }
        if ($content === '') {
            return self::refusal('<strong>Error:</strong> Please type your comment text.', 200);
        }
        if ($this->comments->duplicate($post->id, $author, $email, $content, $user === null ? 0 : (int) $user['ID'])) {
            return self::refusal('Duplicate comment detected; it looks as though you&#8217;ve already said that!', 409);
        }
        if ($this->comments->flooding($email, $request->remoteAddress, self::FLOOD_SECONDS)) {
            return self::refusal('You are posting comments too quickly. Slow down.', 429);
        }
        $content = Kses::filter($content, Kses::COMMENT);
        $content = (string) preg_replace_callback('/<a\s([^>]*)>/i', static function (array $m): string {
            $attributes = preg_replace('/\s*\brel\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $m[1]);
            return '<a ' . trim((string) $attributes) . ' rel="nofollow ugc">';
        }, $content);
        $approved = $this->approval($user, $author, $email);
        $id = $this->comments->insert([
            'comment_post_ID' => $post->id,
            'comment_author' => $author,
            'comment_author_email' => $email,
            'comment_author_url' => $url,
            'comment_author_IP' => $request->remoteAddress,
            'comment_date' => $this->site->localNow(),
            'comment_date_gmt' => gmdate('Y-m-d H:i:s'),
            'comment_content' => $content,
            'comment_karma' => 0,
            'comment_approved' => $approved,
            'comment_agent' => substr((string) ($request->header('user-agent') ?? ''), 0, 254),
            'comment_type' => 'comment',
            'comment_parent' => max(0, (int) ($request->form['comment_parent'] ?? 0)),
            'user_id' => $user === null ? 0 : (int) $user['ID'],
        ]);
        $permalink = $this->permalinks->forPost($post);
        $remember = $user === null && ((string) ($request->form['wp-comment-cookies-consent'] ?? '') !== '' || ($this->site->option('show_comments_cookies_opt_in') ?? '1') !== '1');
        if ($approved === '1') {
            $this->comments->recount($post->id);
            $location = $permalink . '#comment-' . $id;
        } else {
            // A held comment is found again through the author cookies when they are set;
            // otherwise the reference hands the reader the unapproved id and a hash.
            $location = $permalink . ($remember ? '' : '?unapproved=' . $id . '&moderation-hash=' . $this->moderationHash($id)) . '#comment-' . $id;
            if (($this->site->option('moderation_notify') ?? '1') === '1') {
                $this->notifyModerator($post, $content, $author);
            }
        }
        $response = Response::redirect($location, 302);
        return $remember ? $this->rememberAuthor($response, $author, $email, $url, $request->secure) : $response;
    }

    /** A moderator's own comment is approved; otherwise the moderation settings decide. */
    private function approval(?array $user, string $author, string $email): string
    {
        $moderator = $user !== null && $this->capabilities->can((int) $user['ID'], 'moderate_comments');
        return (new CommentModeration($this->comments))->approval($moderator, $author, $email, fn (string $name): ?string => $this->site->option($name));
    }

    /** The reference remembers the commenter for a year, in three cookies keyed by the site hash. */
    private function rememberAuthor(Response $response, string $author, string $email, string $url, bool $secure): Response
    {
        $hash = $this->cookies->hash();
        $options = ['expires' => time() + 31536000, 'path' => '/', 'secure' => $secure, 'samesite' => 'Lax'];
        return $response
            ->withCookie('comment_author_' . $hash, $author, $options)
            ->withCookie('comment_author_email_' . $hash, $email, $options)
            ->withCookie('comment_author_url_' . $hash, $url, $options);
    }

    private function moderationHash(int $commentId): string
    {
        return substr(hash_hmac('md5', 'comment-' . $commentId, \Minn\Auth\Salts::for('nonce')), 0, 32);
    }

    private function notifyModerator(PostRecord $post, string $content, string $author): void
    {
        $notice = Mailer::noticesFor($this->site)->moderation((string) ($this->site->option('admin_email') ?? ''), $post->title, $author, $content);
        Mailer::forSite($this->site)->send($notice);
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
