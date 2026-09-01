<?php

declare(strict_types=1);

namespace Minn\Rest;

use Minn\Content\Blocks;
use Minn\Content\Comments;
use Minn\Content\Posts;
use Minn\Front\Permalinks;

/** The wp/v2 comment object; edit context adds the moderation-desk fields. */
final readonly class CommentObject
{
    public function __construct(
        private Comments $comments,
        private Posts $posts,
        private Permalinks $permalinks,
        private RestUrl $url,
        private Caller $caller,
    ) {
    }

    public function url(): RestUrl
    {
        return $this->url;
    }

    public function build(array $c, bool $edit): array
    {
        $id = (int) $c['comment_ID'];
        $postId = (int) $c['comment_post_ID'];
        $post = $this->posts->find($postId);
        $rendered = Blocks::paragraphs((string) $c['comment_content']);

        $object = [
            'id' => $id,
            'post' => $postId,
            'parent' => (int) $c['comment_parent'],
            'author' => (int) $c['user_id'],
            'author_name' => $c['comment_author'],
        ];
        if ($edit) {
            $object['author_email'] = $c['comment_author_email'];
            $object['author_url'] = $c['comment_author_url'];
            $object['author_ip'] = $c['comment_author_IP'];
            $object['author_user_agent'] = $c['comment_agent'];
        } else {
            $object['author_url'] = $c['comment_author_url'];
        }
        $object['date'] = PostObject::date((string) $c['comment_date']);
        $object['date_gmt'] = PostObject::date((string) $c['comment_date_gmt']);
        $object['content'] = $edit ? ['rendered' => $rendered, 'raw' => $c['comment_content']] : ['rendered' => $rendered];
        $object['link'] = ($post === null ? $this->url->home('/?p=' . $postId) : $this->permalinks->forPost($post)) . '#comment-' . $id;
        $object['status'] = Comments::statusOf((string) $c['comment_approved']);
        $object['type'] = $c['comment_type'] === '' ? 'comment' : $c['comment_type'];
        $object['author_avatar_urls'] = UserObject::avatarUrls((string) $c['comment_author_email']);
        $object['meta'] = ['_wp_note_status' => $this->comments->meta($id, '_wp_note_status')];

        $links = [
            'self' => [[
                'href' => $this->url->to('/wp/v2/comments/' . $id),
                'targetHints' => ['allow' => $this->caller->can('moderate_comments') ? ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'] : ['GET']],
            ]],
            'collection' => [['href' => $this->url->to('/wp/v2/comments')]],
        ];
        if ((int) $c['user_id'] > 0) {
            $links['author'] = [['embeddable' => true, 'href' => $this->url->to('/wp/v2/users/' . (int) $c['user_id'])]];
        }
        if ($postId > 0) {
            $type = $post->type ?? 'post';
            $links['up'] = [[
                'embeddable' => true,
                'post_type' => $type,
                'href' => $this->url->to('/wp/v2/' . PostObject::restBase((string) $type) . '/' . $postId),
            ]];
        }
        if ((int) $c['comment_parent'] > 0) {
            $links['in-reply-to'] = [['embeddable' => true, 'href' => $this->url->to('/wp/v2/comments/' . (int) $c['comment_parent'])]];
        }
        $object['_links'] = $links;
        return $object;
    }
}
