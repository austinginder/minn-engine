<?php

declare(strict_types=1);

namespace Minn\Rest;

use Minn\Content\CommentRecord;
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
        // The content passes comment_text, as the reference renders it.
        $rendered = (string) \apply_filters('comment_text', $c->content, \get_comment($c->id), []);

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
        $object['meta'] = RestMeta::read('comment', $id, 'comment', $context->value);

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
        // Replies are counted through the comment query (any approval, any type), as plugins' clauses see it.
        if ((int) \get_comments(['count' => true, 'orderby' => 'none', 'parent' => $id, 'type' => 'all']) > 0) {
            $links['children'] = [['embeddable' => true, 'href' => $this->url->to('/wp/v2/comments') . '?parent=' . $id]];
        }
        $object['_links'] = $links;
        return $object;
    }
}
