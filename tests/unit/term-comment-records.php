<?php

declare(strict_types=1);

use Minn\Content\CommentRecord;
use Minn\Content\TermRecord;

return [
    'a term row becomes typed properties, taxonomy optional' => static function () {
        $full = TermRecord::fromRow(['term_id' => '3', 'name' => 'News', 'slug' => 'news', 'term_taxonomy_id' => '3', 'taxonomy' => 'category', 'parent' => '0', 'count' => '2']);
        $bare = TermRecord::fromRow(['term_id' => '4', 'name' => 'Sub', 'slug' => 'sub', 'term_taxonomy_id' => '4', 'description' => 'd', 'count' => '0', 'parent' => '3']);
        return $full->id === 3 && $full->taxonomy === 'category' && $full->count === 2 && !$full->hasParent()
            && $bare->taxonomy === '' && $bare->description === 'd' && $bare->hasParent() && $bare->parentId === 3;
    },
    'a comment row becomes typed properties and answers its status' => static function () {
        $c = CommentRecord::fromRow(['comment_ID' => '9', 'comment_post_ID' => '1', 'comment_author' => 'A', 'comment_approved' => '1', 'comment_type' => '', 'comment_parent' => '0', 'user_id' => '0']);
        $held = CommentRecord::fromRow(['comment_approved' => '0', 'comment_type' => 'comment']);
        return $c->id === 9 && $c->postId === 1 && $c->isApproved() && $c->isComment() && !$c->isPending()
            && $held->isPending() && $held->isComment();
    },
    'both keep their rows and bridge bracket reads' => static function () {
        $t = TermRecord::fromRow(['term_id' => 1, 'slug' => 'a']);
        $c = CommentRecord::fromRow(['comment_ID' => 2]);
        return $t['slug'] === 'a' && $t->row() === ['term_id' => 1, 'slug' => 'a'] && $c['comment_ID'] === 2 && $c['nope'] === null;
    },
];
