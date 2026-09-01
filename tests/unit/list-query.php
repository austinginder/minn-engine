<?php

declare(strict_types=1);

use Minn\Http\Method;
use Minn\Http\Request;
use Minn\Media\Upload;
use Minn\Rest\ListQuery;

$request = static fn (array $query, array $headers = [], string $body = '', array $files = [], array $form = []) => new Request(Method::Get, '/wp-json/wp/v2/posts', $query, $headers, [], $body, false, 'minn.localhost', $form, $files);

return [
    'defaults are page one of ten, newest first' => static function () use ($request) {
        $q = ListQuery::fromRequest($request([]));
        return $q->page === 1 && $q->perPage === 10 && $q->order === 'DESC' && $q->orderBy === 'date' && $q->clauses() === ['', []] && !$q->isSearch();
    },
    'per_page is clamped to 1..100 and page to 1' => static function () use ($request) {
        $q = ListQuery::fromRequest($request(['per_page' => '500', 'page' => '-3']));
        return $q->perPage === 100 && $q->page === 1 && $q->offset() === 0;
    },
    'ids drop blanks, non-digits, duplicates, and zero unless kept' => static fn () => ListQuery::ids(' 3,,x,3,0,7') === [3, 7] && ListQuery::ids('0,4', true) === [0, 4],
    'clauses follow the fields with their parameters in the same order' => static function () use ($request) {
        $q = ListQuery::fromRequest($request(['author' => '2', 'include' => '5,6', 'slug' => 'a,,b', 'parent' => '0', 'search' => ' two  words ']));
        [$where, $params] = $q->clauses();
        return $where === ' AND post_author IN (?) AND ID IN (?) AND post_name IN (?) AND post_parent IN (?)'
            . ' AND (post_title LIKE ? OR post_excerpt LIKE ? OR post_content LIKE ?)'
            . ' AND (post_title LIKE ? OR post_excerpt LIKE ? OR post_content LIKE ?)'
            && $params === [[2], [5, 6], ['a', 'b'], [0], '%two%', '%two%', '%two%', '%words%', '%words%', '%words%']
            && $q->isSearch();
    },
    'search words escape the LIKE wildcards' => static function () use ($request) {
        [, $params] = ListQuery::fromRequest($request(['search' => '50%_off']))->clauses();
        return $params === ['%50\\%\\_off%', '%50\\%\\_off%', '%50\\%\\_off%'];
    },
    'menu_order is an integer or nothing' => static function () use ($request) {
        return ListQuery::fromRequest($request(['menu_order' => '-2']))->menuOrder === -2
            && ListQuery::fromRequest($request(['menu_order' => 'x']))->menuOrder === null
            && ListQuery::fromRequest($request([]))->menuOrder === null;
    },
    'paging: totals round up and a page past the end is an error, page one of nothing is not' => static function () use ($request) {
        $q = ListQuery::fromRequest($request(['per_page' => '4', 'page' => '3']));
        return $q->totalPages(9) === 3 && !$q->isPastTheEnd(9) && $q->isPastTheEnd(8) && $q->offset() === 8
            && !ListQuery::fromRequest($request([]))->isPastTheEnd(0);
    },
    'an upload comes from the multipart field first' => static function () use ($request) {
        $u = Upload::fromRequest($request([], [], '', ['file' => ['name' => 'Shot.PNG', 'tmp_name' => '/tmp/x']], ['post' => '12']));
        return $u instanceof Upload && $u->filename === 'Shot.PNG' && $u->movedFrom === '/tmp/x' && $u->raw === null
            && $u->parent === 12 && $u->mime() === 'image/png' && $u->isImage();
    },
    'or from a raw body named by Content-Disposition, parent in the query' => static function () use ($request) {
        $u = Upload::fromRequest($request(['post' => '3'], ['content-disposition' => 'attachment; filename="notes.pdf"'], '%PDF'));
        return $u instanceof Upload && $u->filename === 'notes.pdf' && $u->raw === '%PDF' && $u->movedFrom === null
            && $u->parent === 3 && $u->mime() === 'application/pdf' && !$u->isImage();
    },
    'no name or no bytes is no upload' => static function () use ($request) {
        return Upload::fromRequest($request([], ['content-disposition' => 'attachment; filename="a.png"'], '')) === null
            && Upload::fromRequest($request([], [], 'bytes')) === null;
    },
    'an unknown extension has no mime and an svg is not an image' => static function () use ($request) {
        $exe = Upload::fromRequest($request([], ['content-disposition' => 'attachment; filename="run.exe"'], 'x'));
        $svg = Upload::fromRequest($request([], ['content-disposition' => 'attachment; filename="logo.svg"'], 'x'));
        return $exe?->mime() === null && $svg !== null && !$svg->isImage();
    },
];
