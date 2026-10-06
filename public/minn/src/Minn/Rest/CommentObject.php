<?php

declare(strict_types=1);

namespace Minn\Rest;

use Minn\Runtime\Runtime;
use Minn\Content\CommentRecord;
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


    /** A comment in the context asked for, through rest_prepare_comment when a plugin hooks it. */
    public function build(CommentRecord $c, Context $context): array
    {
        return RuntimePrepare::item('rest_prepare_comment', $this->buildFields($c, $context), static fn () => \get_comment($c->id));
    }

    /** The REST URL builder. */
    public function url(): RestUrl
    {
        return $this->url;
    }

    /** The wp/v2 comment shape, with the edit-context fields when asked. */
    private function buildFields(CommentRecord $c, Context $context): array
    {
        $edit = $context->isEdit();
        $id = $c->id;
        $postId = $c->postId;
        $post = $this->posts->find($postId);
        // With plugins loaded the content passes comment_text, as the reference renders it; without, Minn's own paragraphs.
        $rendered = Runtime::booted() ? (string) \apply_filters('comment_text', $c->content, \get_comment($c->id), []) : Blocks::paragraphs($c->content);

        $object = [
            'id' => $id,
            'post' => $postId,
            'parent' => $c->parentId,
            'author' => $c->userId,
            'author_name' => $c->author,
        ];
        if ($edit) {
            $object['author_email'] = $c->authorEmail;
            $object['author_url'] = $c->authorUrl;
            $object['author_ip'] = $c->authorIp;
            $object['author_user_agent'] = $c->agent;
        } else {
            $object['author_url'] = $c->authorUrl;
        }
        $object['date'] = PostObject::date($c->date);
        $object['date_gmt'] = PostObject::date($c->dateGmt);
        $object['content'] = $edit ? ['rendered' => $rendered, 'raw' => $c->content] : ['rendered' => $rendered];
        $object['link'] = ($post === null ? $this->url->home('/?p=' . $postId) : $this->permalinks->forPost($post)) . '#comment-' . $id;
        $object['status'] = Comments::statusOf($c->approved);
        $object['type'] = $c->type === '' ? 'comment' : $c->type;
        $object['author_avatar_urls'] = UserObject::avatarUrls($c->authorEmail);
        $object['meta'] = ['_wp_note_status' => $this->comments->meta($id, '_wp_note_status')];

        $links = [
            'self' => [[
                'href' => $this->url->to('/wp/v2/comments/' . $id),
                'targetHints' => ['allow' => $this->caller->can('moderate_comments') ? ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'] : ['GET']],
            ]],
            'collection' => [['href' => $this->url->to('/wp/v2/comments')]],
        ];
        if ($c->userId > 0) {
            $links['author'] = [['embeddable' => true, 'href' => $this->url->to('/wp/v2/users/' . $c->userId)]];
        }
        if ($postId > 0) {
            $type = $post->type ?? 'post';
            $links['up'] = [[
                'embeddable' => true,
                'post_type' => $type,
                'href' => $this->url->to('/wp/v2/' . PostObject::restBase((string) $type) . '/' . $postId),
            ]];
        }
        if ($c->parentId > 0) {
            $links['in-reply-to'] = [['embeddable' => true, 'href' => $this->url->to('/wp/v2/comments/' . $c->parentId)]];
        }
        $object['_links'] = $links;
        return $object;
    }
}
