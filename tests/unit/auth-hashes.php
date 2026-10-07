<?php

declare(strict_types=1);

use Minn\Auth\FastHash;
use Minn\Auth\PortableHash;

/**
 * The hashes secrets are stored under. The $generic$ values are what the
 * reference's wp_fast_hash() returned for these inputs (captured
 * 2026-10-05); cross-stack use is pinned by the cron-mail and
 * application-passwords suites.
 */
$captured = [
    'abc' => '$generic$YeaPr_itxnUOyFBBm16EGFnt_nzqpbL728cakJ4U',
    '' => '$generic$XJQGK2eUryTa4vHbU0bDv1Ml3wGPwaC1ATczq0o_',
    'password reset key 12345' => '$generic$YgstgTnxPRnmJaPbhmrdE99muMPedA6a1K9ou1v2',
];

return [
    'the fast hash is the reference\'s byte for byte' => static function () use ($captured): bool {
        foreach ($captured as $secret => $hash) {
            if (FastHash::hash((string) $secret) !== $hash) {
                return false;
            }
        }
        return true;
    },
    'a fast hash verifies its secret and nothing else' => static fn () => FastHash::verify('abc', $captured['abc']) && !FastHash::verify('abd', $captured['abc']) && !FastHash::verify('abc', PortableHash::hash('abc')),
    'phpass still verifies (keys and passwords from before WordPress 6.8)' => static fn () => PortableHash::verify('secret', PortableHash::hash('secret')) && !PortableHash::verify('other', PortableHash::hash('secret')),
];
