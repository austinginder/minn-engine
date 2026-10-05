<?php

declare(strict_types=1);

use Minn\Auth\Sessions;
use Minn\Content\Users;
use Minn\Runtime\Hooks;

/**
 * Small rules the round trip (tests/round-trip.test.php) caught: what a
 * write leaves behind has to be what the reference leaves.
 */
$stored = 'a:4:{s:10:"expiration";i:1790000000;s:2:"ip";s:9:"127.0.0.1";s:2:"ua";s:4:"curl";s:5:"login";i:1789000000;}';
$withExtra = 'a:5:{s:10:"expiration";i:1790000000;s:2:"ip";s:9:"127.0.0.1";s:2:"ua";s:0:"";s:5:"login";i:1789000000;s:6:"plugin";a:1:{s:1:"x";i:1;}}';

return [
    'a stored session is written back exactly as it was read, whatever a plugin attached' => static function () use ($stored, $withExtra): bool {
        $key = str_repeat('a', 64);
        $other = str_repeat('b', 64);
        $blob = 'a:2:{s:64:"' . $key . '";' . $stored . 's:64:"' . $other . '";' . $withExtra . '}';
        return Sessions::serialize([$key => ['raw' => $stored], $other => ['raw' => $withExtra]]) === $blob;
    },
    'a new session without a user agent has no ua key' => static fn (): bool => Sessions::serialize(['k' => ['expiration' => 5, 'ip' => '127.0.0.1', 'login' => 4]])
        === 'a:1:{s:1:"k";a:3:{s:10:"expiration";i:5;s:2:"ip";s:9:"127.0.0.1";s:5:"login";i:4;}}',
    'a new account is named first and last, either one, or by its login' => static fn (): bool => Users::defaultDisplayName('Ada', 'Lovelace', 'cap0') === 'Ada Lovelace'
        && Users::defaultDisplayName('Grace', '', 'cap1') === 'Grace'
        && Users::defaultDisplayName('', 'Hopper', 'cap2') === 'Hopper'
        && Users::defaultDisplayName('', '', 'cap3') === 'cap3',
    'an action fired with nothing hands its callbacks one empty string; the array form hands them nothing' => static function (): bool {
        $hooks = new Hooks();
        $seen = [];
        $hooks->add('probe', static function (...$args) use (&$seen): void {
            $seen[] = $args;
        }, 10, 2);
        $hooks->action('probe', []);
        $hooks->actionRef('probe', []);
        $hooks->action('probe', ['x']);
        return $seen === [[''], [], ['x']];
    },
];
