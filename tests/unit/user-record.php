<?php

declare(strict_types=1);

use Minn\Content\UserRecord;

$row = ['ID' => '2', 'user_login' => 'editor', 'user_pass' => '$wp$2y$10$x', 'user_nicename' => 'editor', 'user_email' => 'editor@minn-engine.localhost', 'user_url' => '', 'user_registered' => '2026-08-28 02:22:07', 'user_activation_key' => '', 'user_status' => '0', 'display_name' => 'Erin Editor'];

return [
    'a row becomes typed properties' => static function () use ($row) {
        $user = UserRecord::fromRow($row);
        return $user->id === 2 && $user->login === 'editor' && $user->email === 'editor@minn-engine.localhost' && $user->displayName === 'Erin Editor' && $user->status === 0;
    },
    'the row comes back whole' => static fn () => UserRecord::fromRow($row)->row() === $row,
    'name() falls back to the login' => static fn () => UserRecord::fromRow($row)->name() === 'Erin Editor' && UserRecord::fromRow(['display_name' => ''] + $row)->name() === 'editor',
    'bracket reads still work during the migration' => static fn () => UserRecord::fromRow($row)['user_login'] === 'editor' && UserRecord::fromRow($row)['nope'] === null,
    'bracket writes are refused' => static function () use ($row) {
        try {
            $user = UserRecord::fromRow($row);
            $user['user_login'] = 'no';
            return 'wrote';
        } catch (LogicException) {
            return true;
        }
    },
];
