<?php

declare(strict_types=1);

use Minn\Content\Posts;
use Minn\Content\Users;
use Minn\Db;
use Minn\Http\Method;
use Minn\Http\Request;
use Minn\Rest\Services;

// An unconnected handle: every service here is made lazily, so nothing touches it.
$services = static fn () => Services::forRequest(new Db(new mysqli(), 'wp_'), new Request(Method::Get, '/', [], [], [], '', false, 'minn.localhost'));

return [
    'a service is made once and shared' => static function () use ($services) {
        $s = $services();
        return $s->posts() === $s->posts() && $s->users() instanceof Users && $s->writer() === $s->writer();
    },
    'get() answers by class name with the same instance' => static function () use ($services) {
        $s = $services();
        return $s->get(Posts::class) === $s->posts() && $s->get(Users::class) === $s->users();
    },
    'get() fails loudly for a name nobody registered' => static function () use ($services) {
        try {
            $services()->get(\DateTimeImmutable::class);
        } catch (LogicException $e) {
            return str_contains($e->getMessage(), 'DateTimeImmutable') && str_contains($e->getMessage(), 'Services::NAMED');
        }
        return 'no exception';
    },
    'the site root and content dir come from ABSPATH' => static function () use ($services) {
        $s = $services();
        return $s->root() === rtrim(ABSPATH, '/') && $s->contentDir() === rtrim(ABSPATH, '/') . '/wp-content';
    },
];
