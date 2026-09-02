<?php

declare(strict_types=1);

use Minn\Content\CommentFilter;

return [
    'the default filter is plain comments on every post' => static fn () => CommentFilter::all()->isPlainType() && CommentFilter::all()->post === [] && !CommentFilter::all()->publicPostsOnly,
    'onPublicPosts keeps every field and sets the one flag' => static function () {
        $f = new CommentFilter(post: [0, 3], type: 'pingback', search: 'x');
        $g = $f->onPublicPosts();
        return $g->publicPostsOnly && !$f->publicPostsOnly && $g->post === [0, 3] && $g->type === 'pingback' && $g->search === 'x' && !$g->isPlainType();
    },
];
