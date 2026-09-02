<?php

declare(strict_types=1);

use Minn\Content\Reader;
use Minn\Context;

/**
 * The per-request context: the value the engine builds once at the front
 * door and hands down. Its paths come from the site root it was given,
 * and a surface that resolves its reader later asks for a context
 * carrying that reader rather than mutating this one.
 */
// An unconnected handle is enough: nothing here runs a query.
$db = new Minn\Db(new mysqli(), 'wp_');
$context = static fn (?Minn\Http\Request $request = null): Context => new Context(
    $db,
    new Minn\Content\Site($db),
    $request,
    Reader::anonymous('secret'),
    Minn\Auth\Capabilities::fromDb($db),
    '/srv/site/public/minn',
    '/srv/site/public/',
    '7.1',
);
$request = static fn (bool $secure): Minn\Http\Request => new Minn\Http\Request(
    Minn\Http\Method::Get,
    '/',
    [],
    [],
    [],
    '',
    $secure,
    'unit.test',
    [],
);

return [
    'the paths come from the site root, with no trailing slash left over' => static function () use ($context): bool|string {
        $c = $context();
        return $c->contentDir() === '/srv/site/public/wp-content' && $c->themesDir() === '/srv/site/public/wp-content/themes'
            ? true : $c->contentDir() . ' | ' . $c->themesDir();
    },
    'a context with no request is not secure (the command line)' => static function () use ($context): bool {
        return $context()->isSecure() === false;
    },
    'the request decides the scheme' => static function () use ($context, $request): bool|string {
        return $context($request(true))->isSecure() === true && $context($request(false))->isSecure() === false
            ? true : 'scheme wrong';
    },
    'withReader gives a new context and leaves the first alone' => static function () use ($context): bool|string {
        $first = $context();
        $mine = new Reader(7, true, true, static fn (int $id): bool => true, '', 'token', ['editor']);
        $second = $first->withReader($mine);
        return $second !== $first
            && $second->reader === $mine
            && $first->reader->userId === 0
            && $second->db === $first->db
            && $second->site === $first->site
            && $second->capabilities === $first->capabilities
            && $second->engineDir === $first->engineDir
            && $second->absPath === $first->absPath
            && $second->version === $first->version
            ? true : 'withReader did not carry the rest of the context';
    },
    'the reader a context carries answers for itself' => static function () use ($context): bool|string {
        $anonymous = $context()->reader;
        $editor = $context()->withReader(new Reader(7, true, false, static fn (int $id): bool => $id === 5, '', '', ['editor']))->reader;
        return !$anonymous->loggedIn()
            && $anonymous->postPassword === 'secret'
            && $editor->loggedIn()
            && $editor->canEdit(5)
            && !$editor->canEdit(6)
            && $editor->roles === ['editor']
            ? true : 'reader wrong';
    },
];
