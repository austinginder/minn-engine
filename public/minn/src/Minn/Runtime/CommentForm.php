<?php

declare(strict_types=1);

namespace Minn\Runtime;

/**
 * The comment form's submission with plugins loaded
 * (wp_handle_comment_submission), in the order the reference runs it
 * (contracts/runtime.md "The comment form"): a reply to a comment still
 * held is refused; the post is looked at, each refusal with its action
 * (comment_id_not_found, comment_closed, comment_on_trash, comment_on_draft,
 * comment_on_password_protected) and pre_comment_on_post when it takes
 * comments; the signed-in user's own name and addresses replace the form's,
 * or a site that requires sign-in refuses; the required fields, an empty
 * comment (allow_empty_comment) and the column lengths are checked; then
 * wp_new_comment stores it, where preprocess_comment, the duplicate and
 * flood checks and pre_comment_approved have their say. A refusal is a
 * WP_Error whose data is the status the form answers with; none means a
 * blank page.
 */
final class CommentForm
{
    /**
     * The stored comment, or the refusal the form answers with.
     *
     * @param array<string, mixed> $form the posted fields, unslashed
     */
    public static function submit(array $form): \WP_Comment|\WP_Error
    {
        $text = static fn (string $key): string => isset($form[$key]) && is_scalar($form[$key]) ? trim((string) $form[$key]) : '';
        $postId = (int) $text('comment_post_ID');
        $author = trim(strip_tags($text('author')));
        $email = $text('email');
        $url = $text('url');
        $content = $text('comment');
        $parent = \absint($text('comment_parent'));
        if ($parent > 0) {
            $replied = \get_comment($parent);
            if (!$replied instanceof \WP_Comment || (string) $replied->comment_approved === '0') {
                \do_action('comment_reply_to_unapproved_comment', $postId, $parent);
                return new \WP_Error('comment_reply_to_unapproved_comment', 'Sorry, replies to unapproved comments are not allowed.', 403);
            }
        }
        $refusal = self::postRefusal($postId);
        if ($refusal !== null) {
            return $refusal;
        }
        $user = \wp_get_current_user();
        if ($user->exists()) {
            $author = (string) $user->display_name !== '' ? (string) $user->display_name : (string) $user->user_login;
            $email = (string) $user->user_email;
            $url = (string) $user->user_url;
            // An administrator's comment is filtered after all unless the form carried its unfiltered-html nonce.
            if (\current_user_can('unfiltered_html') && !\wp_verify_nonce((string) ($form['_wp_unfiltered_html_comment'] ?? ''), 'unfiltered-html-comment_' . $postId)) {
                \kses_remove_filters();
                \kses_init_filters();
            }
        } elseif (\get_option('comment_registration')) {
            return new \WP_Error('not_logged_in', 'Sorry, you must be logged in to comment.', 403);
        }
        $missing = self::fieldRefusal($user, $author, $email);
        if ($missing !== null) {
            return $missing;
        }
        $data = ['comment_post_ID' => $postId, 'comment_author' => $author, 'comment_author_email' => $email, 'comment_author_url' => $url, 'comment_content' => $content, 'comment_type' => 'comment', 'comment_parent' => $parent, 'user_id' => (int) $user->ID];
        return self::store($data, $content);
    }

    /** The comment checked for content and length, then stored through wp_new_comment. @param array<string, mixed> $data */
    private static function store(array $data, string $content): \WP_Comment|\WP_Error
    {
        if ($content === '' && !\apply_filters('allow_empty_comment', false, $data)) {
            return new \WP_Error('require_valid_comment', '<strong>Error:</strong> Please type your comment text.', 200);
        }
        $lengths = \wp_check_comment_data_max_lengths($data);
        if (\is_wp_error($lengths)) {
            return $lengths;
        }
        $id = \wp_new_comment(\wp_slash($data), true);
        if (\is_wp_error($id)) {
            return $id;
        }
        $comment = $id ? \get_comment($id) : null;
        return $comment instanceof \WP_Comment ? $comment : new \WP_Error('comment_save_error', '<strong>Error:</strong> The comment could not be saved. Please try again later.', 500);
    }

    /** Why the post takes no comment, or null after pre_comment_on_post when it does. */
    private static function postRefusal(int $postId): ?\WP_Error
    {
        $post = \get_post($postId);
        if ($post === null || (string) $post->comment_status === '') {
            \do_action('comment_id_not_found', $postId);
            return new \WP_Error('comment_id_not_found');
        }
        $status = (string) \get_post_status($post);
        if ($status === 'private' && !\current_user_can('read_post', $postId)) {
            return new \WP_Error('comment_id_not_found');
        }
        $statusObject = \get_post_status_object($status);
        $refusal = match (true) {
            !\comments_open($postId) => ['comment_closed', 'Sorry, comments are closed for this item.', 403],
            $status === 'trash' => ['comment_on_trash', '', 0],
            $statusObject === null || (!$statusObject->public && !$statusObject->private) => \current_user_can('read_post', $postId) ? ['comment_on_draft', 'Sorry, comments are not allowed for this item.', 403] : ['comment_on_draft', '', 0],
            \post_password_required($postId) => ['comment_on_password_protected', '', 0],
            default => null,
        };
        if ($refusal === null) {
            \do_action('pre_comment_on_post', $postId);
            return null;
        }
        [$code, $message, $answer] = $refusal;
        \do_action($code, $postId);
        return $answer > 0 ? new \WP_Error($code, $message, $answer) : new \WP_Error($code);
    }

    /** A signed-out commenter's name and email, when the site requires them. */
    private static function fieldRefusal(\WP_User $user, string $author, string $email): ?\WP_Error
    {
        if (!\get_option('require_name_email') || $user->exists()) {
            return null;
        }
        if ($email === '' || $author === '') {
            return new \WP_Error('require_name_email', '<strong>Error:</strong> Please fill the required fields.', 200);
        }
        return \is_email($email) ? null : new \WP_Error('require_valid_email', '<strong>Error:</strong> Please enter a valid email address.', 200);
    }
}
