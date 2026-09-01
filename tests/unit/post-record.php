<?php

declare(strict_types=1);

use Minn\Content\PostRecord;
use Minn\Content\PostStatus;

$row = ['ID' => '7', 'post_author' => '2', 'post_title' => 'Hello', 'post_name' => 'hello', 'post_status' => 'publish', 'post_type' => 'post', 'post_password' => '', 'post_parent' => '0', 'post_content' => '<p>x</p>', 'post_date' => '2026-01-01 00:00:00', 'term_taxonomy_id' => '3'];

return [
    'a row becomes typed properties' => static function () use ($row) {
        $post = PostRecord::fromRow($row);
        return $post->id === 7 && $post->authorId === 2 && $post->title === 'Hello' && $post->slug === 'hello' && $post->type === 'post';
    },
    'the row comes back whole, joined columns included' => static fn () => PostRecord::fromRow($row)->row() === $row && PostRecord::fromRow($row)->column('term_taxonomy_id') === '3',
    'status reads as an enum and as questions' => static function () use ($row) {
        $post = PostRecord::fromRow($row);
        $draft = PostRecord::fromRow(['post_status' => 'draft'] + $row);
        $odd = PostRecord::fromRow(['post_status' => 'acme-queued'] + $row);
        return $post->status() === PostStatus::Publish && $post->isPublished() && $post->isLive()
            && !$draft->isLive() && !$draft->isPublished() && $odd->status() === null && !$odd->isLive();
    },
    'a missing column reads as its zero value' => static fn () => PostRecord::fromRow([])->id === 0 && PostRecord::fromRow([])->title === '' && PostRecord::fromRow([])->menuOrder === 0,
    'bracket reads still work during the migration' => static fn () => PostRecord::fromRow($row)['post_title'] === 'Hello' && isset(PostRecord::fromRow($row)['ID']) && PostRecord::fromRow($row)['nope'] === null,
    'bracket writes are refused' => static function () use ($row) {
        try {
            $post = PostRecord::fromRow($row);
            $post['post_title'] = 'no';
            return 'wrote';
        } catch (LogicException) {
            return true;
        }
    },
    'protected and page read as questions' => static fn () => PostRecord::fromRow(['post_password' => 'x', 'post_type' => 'page'] + $row)->isProtected() && PostRecord::fromRow(['post_type' => 'page'] + $row)->isPage() && !PostRecord::fromRow($row)->isProtected(),
];
