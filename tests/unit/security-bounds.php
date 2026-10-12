<?php

declare(strict_types=1);

use Minn\Auth\PortableHash;
use Minn\Content\PasswordGate;
use Minn\Db;
use Minn\Support\Serialized;

/**
 * Bounds the 2026-10-12 audit found missing: a password cookie that names
 * its own work factor, serialized data that loops the decoder, and the SQL
 * mode the engine's literal escaping relies on.
 */
$quick = static function (callable $run): bool {
    $start = microtime(true);
    $run();
    return microtime(true) - $start < 0.2;
};

return [
    'a password cookie opens the post only in the reference\'s hash setting, and another one is refused without hashing' => static function () use ($quick): bool {
        $cookie = PortableHash::hash('secret');
        $costly = '$P$S' . substr($cookie, 4);
        return str_starts_with($cookie, '$P$B') && PasswordGate::cookieUnlocks('secret', $cookie) && !PasswordGate::cookieUnlocks('other', $cookie)
            && $quick(static fn () => PasswordGate::cookieUnlocks('secret', $costly)) && !PasswordGate::cookieUnlocks('secret', $costly) && !PasswordGate::cookieUnlocks('secret', '');
    },
    'the decoder refuses a negative length, a count the data cannot hold, and nesting past PHP\'s depth, at once' => static function () use ($quick): bool {
        $blobs = [
            'a:9223372036854775807:{s:0:"";N;s:-11:"";}',
            's:-3:"abc";',
            'a:999999:{i:0;N;}',
            'O:8:"stdClass":99999999:{}',
            'O:-1:"x":0:{}',
            str_repeat('a:1:{i:0;', 5000) . 'N;' . str_repeat('}', 5000),
        ];
        foreach ($blobs as $blob) {
            if (!$quick(static fn () => Serialized::decode($blob)) || Serialized::decode($blob) !== Serialized::INVALID) {
                return false;
            }
        }
        $value = ['a' => [1, 2.5, true, null], 'b' => 'x"y', 7 => ['nested' => ['deeper' => 'z']]];
        return Serialized::decode(serialize($value)) === $value && Serialized::decode(str_repeat('a:1:{i:0;', 100) . 'N;' . str_repeat('}', 100)) !== Serialized::INVALID;
    },
    'a serialized-looking string has the shape is_serialized() judges' => static fn () => Serialized::looksSerialized('a:1:{s:1:"x";s:1:"y";}') && Serialized::looksSerialized(' N; ') && Serialized::looksSerialized('s:1:"x";') && !Serialized::looksSerialized('nope') && !Serialized::looksSerialized('a:'),
    'the session drops NO_BACKSLASH_ESCAPES with the reference\'s incompatible modes, whatever the server sets' => static fn () => Db::sessionModes('STRICT_TRANS_TABLES,no_backslash_escapes,ERROR_FOR_DIVISION_BY_ZERO,ONLY_FULL_GROUP_BY') === 'ERROR_FOR_DIVISION_BY_ZERO'
        && !in_array('NO_BACKSLASH_ESCAPES', Db::INCOMPATIBLE_MODES, true),
];
