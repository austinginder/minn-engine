<?php

declare(strict_types=1);

use Minn\Content\Page;
use Minn\Content\PostFilter;

return [
    'the default filter is published posts of type post' => static fn () => PostFilter::all()->types === ['post'] && PostFilter::all()->term === null,
    'types() with nothing falls back to post' => static fn () => PostFilter::types()->types === ['post'],
    'every narrowing returns a new filter and keeps the rest' => static function () {
        $base = PostFilter::types('page', 'post');
        $narrow = $base->inTerm(7)->byAuthor(2)->between('2026-01-01', '2026-02-01')->matching('hello');
        return $base->term === null
            && $narrow->types === ['page', 'post'] && $narrow->term === 7 && $narrow->author === 2
            && $narrow->from === '2026-01-01' && $narrow->to === '2026-02-01' && $narrow->search === 'hello'
            && $narrow->hasDates() && !$base->hasDates();
    },
    'a page counts its rows and knows its ids' => static function () {
        $page = new Page([['ID' => '4', 'post_title' => 'a'], ['ID' => 9]], 23);
        return $page->count() === 2 && $page->ids() === [4, 9] && $page->total === 23 && !$page->isEmpty();
    },
    'total pages rounds up and survives a zero page size' => static fn () => (new Page([], 23))->totalPages(10) === 3 && (new Page([], 23))->totalPages(0) === 0,
    'withPosts keeps the total and reindexes' => static function () {
        $page = (new Page([['ID' => 1], ['ID' => 2]], 9))->withPosts([5 => ['ID' => 2]]);
        return $page->posts === [['ID' => 2]] && $page->total === 9;
    },
    'an empty page is empty' => static fn () => Page::empty()->isEmpty() && Page::empty()->total === 0,
];
